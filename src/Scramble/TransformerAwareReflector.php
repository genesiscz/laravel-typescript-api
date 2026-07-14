<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use PawelJadanowski\ScrambleExtras\LaravelDataReflector;
use PawelJadanowski\ScrambleExtras\SchemaCache;
use ReflectionClass;
use ReflectionProperty;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\Optional as TransformerOptional;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType as TypeScriptTypeAttribute;

/**
 * scramble-extras' reflector + spatie/typescript-transformer attribute
 * awareness. Patching happens inside doBuildSchema so it can never be lost;
 * the extras schema cache is bypassed (its entries are written by the
 * unpatched base reflector under the same key — one-shot CLI generation
 * doesn't need it).
 */
class TransformerAwareReflector extends LaravelDataReflector
{
    protected function cache(): ?SchemaCache
    {
        return null;
    }

    protected function doBuildSchema(string $dataClass, bool $input): OpenApiObjectType
    {
        $schema = parent::doBuildSchema($dataClass, $input);

        // #[Optional] (typescript-transformer) means `prop?:` — mirror it by
        // dropping the property from the OpenAPI `required` list.
        $optional = $this->transformerOptionalProperties($dataClass);

        if ($optional !== []) {
            $schema->setRequired(array_values(array_diff($schema->required, $optional)));
        }

        $this->applyTypeScriptTypeAttributes($dataClass, $schema);

        return $schema;
    }

    /**
     * #[TypeScriptType('object')] / #[LiteralTypeScriptType('{...}')]
     * override the inferred type. The raw TS string is stamped as
     * x-typescript-type so the ambient emitter reproduces it verbatim;
     * the OpenAPI schema itself becomes a plain object (for 'object') or
     * an unconstrained schema (for literal type strings).
     */
    protected function applyTypeScriptTypeAttributes(string $dataClass, OpenApiObjectType $schema): void
    {
        foreach ((new ReflectionClass($dataClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attribute = $property->getAttributes(TypeScriptTypeAttribute::class)[0]
                ?? $property->getAttributes(LiteralTypeScriptType::class)[0]
                ?? null;

            if ($attribute === null || ! isset($schema->properties[$property->getName()])) {
                continue;
            }

            $raw = $attribute->getArguments()[0] ?? null;

            if (! is_string($raw)) {
                continue;
            }

            $type = $raw === 'object'
                ? new OpenApiObjectType
                : new \Dedoc\Scramble\Support\Generator\Types\MixedType;

            $type->setExtensionProperty('typescript-type', $raw);

            if ($property->getType()?->allowsNull()) {
                $type->nullable(true);
            }

            $schema->addProperty($property->getName(), $type);
        }
    }

    /**
     * The base reflector resolves native union types (int|bool) to the
     * FIRST member only — emit anyOf over every member instead, so
     * `public int|bool $x` becomes `number | boolean`, not `number`.
     *
     * @param  \ReflectionNamedType[]  $types
     */
    protected function resolveType(array $types, ?string $docVarType, ?FileNameResolver $nameResolver): OpenApiType
    {
        // `null` is carried by allowsNull()/nullable, not as a union member.
        $types = array_values(array_filter(
            $types,
            fn ($type) => $type->getName() !== 'null',
        ));

        if ($docVarType !== null) {
            return parent::resolveType($types, $docVarType, $nameResolver);
        }

        $resolveOne = fn (\ReflectionNamedType $type) => ClassTypeMap::resolve($type->getName())
            ?? $this->degradeUnhandledClass(parent::resolveType([$type], null, $nameResolver));

        if (count($types) < 2) {
            return $types === []
                ? parent::resolveType($types, null, $nameResolver)
                : $resolveOne($types[0]);
        }

        $anyOf = new AnyOf;
        $anyOf->items = array_map($resolveOne, $types);

        return $anyOf;
    }

    /**
     * A union member that is a class NO extension produced a schema for
     * (Stripe SDK objects, Eloquent models) comes back as an inline empty
     * object — typescript-transformer rendered those as `any`, and `any`
     * is what frontend code expects. Real (empty) Data classes come back
     * as References, so they are untouched.
     */
    protected function degradeUnhandledClass(OpenApiType $resolved): OpenApiType
    {
        if ($resolved instanceof OpenApiObjectType && $resolved->properties === []) {
            return new \Dedoc\Scramble\Support\Generator\Types\MixedType;
        }

        return $resolved;
    }

    /**
     * The base regex captures `\S+` — it truncates generic types with
     * spaces (`array<int, Foo>` → `array<int,`), silently degrading items
     * to string. Capture everything up to the parameter name instead.
     */
    protected function findPromotedParamType(ReflectionProperty $property): ?string
    {
        $constructor = $property->getDeclaringClass()->getConstructor();
        $constructorDoc = $constructor?->getDocComment() ?: '';

        if ($constructorDoc === '') {
            return null;
        }

        $name = preg_quote($property->getName(), '/');

        if (preg_match('/@param\s+(.+?)\s+\$'.$name.'\b/', $constructorDoc, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /** @return list<string> */
    protected function transformerOptionalProperties(string $dataClass): array
    {
        $names = [];

        foreach ((new ReflectionClass($dataClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getAttributes(TransformerOptional::class) !== []) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }
}
