<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble\Extensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types as OpenApi;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Spatie\LaravelData\Contracts\BaseData;

/**
 * Schemas for plain classes marked #[TypeScript] (notification payloads,
 * service DTOs) — spatie/typescript-transformer exported these from their
 * public typed properties, so the ambient app.d.ts parity needs them too.
 * Data classes are excluded; the laravel-data pipeline owns those.
 */
class TypeScriptAttributeClassExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && class_exists($type->name)
            && ! is_a($type->name, BaseData::class, true)
            && ! enum_exists($type->name)
            && (new ReflectionClass($type->name))
                ->getAttributes('Spatie\\TypeScriptTransformer\\Attributes\\TypeScript') !== [];
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): OpenApi\Type
    {
        $schema = new OpenApi\ObjectType;
        $required = [];
        $reflection = new ReflectionClass($type->name);
        $traitProps = $this->traitPropertyNames($reflection);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || isset($traitProps[$property->getName()])) {
                continue;
            }

            // Framework base-class props (Notification::$id, $locale, …)
            // never belonged to the exported shape — only app code counts.
            // Trait props (Queueable::$connection, $queue) report the USING
            // class as declaring class, hence the separate name filter.
            $declaringFile = $property->getDeclaringClass()->getFileName();

            if ($declaringFile === false || ! str_starts_with($declaringFile, base_path('app'))) {
                continue;
            }

            $schema->addProperty($property->getName(), $this->propertyType($property));
            $required[] = $property->getName();
        }

        return $schema->setRequired($required);
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array<string, true>
     */
    protected function traitPropertyNames(ReflectionClass $reflection): array
    {
        $names = [];
        $queue = $reflection->getTraits();

        while ($queue !== []) {
            $trait = array_shift($queue);

            foreach ($trait->getProperties() as $property) {
                $names[$property->getName()] = true;
            }

            $queue = array_merge($queue, $trait->getTraits());
        }

        return $names;
    }

    protected function propertyType(ReflectionProperty $property): OpenApi\Type
    {
        $reflectionType = $property->getType();

        $named = $reflectionType instanceof ReflectionUnionType
            ? array_values(array_filter(
                $reflectionType->getTypes(),
                fn ($type) => $type instanceof ReflectionNamedType && $type->getName() !== 'null',
            ))
            : ($reflectionType instanceof ReflectionNamedType ? [$reflectionType] : []);

        $types = array_map(fn (ReflectionNamedType $type) => $this->namedType($type), $named);

        $resolved = match (count($types)) {
            0 => new OpenApi\UnknownType,
            1 => $types[0],
            default => tap(new \Dedoc\Scramble\Support\Generator\Combined\AnyOf, function ($anyOf) use ($types) {
                $anyOf->items = $types;
            }),
        };

        if ($reflectionType?->allowsNull()) {
            $resolved->nullable(true);
        }

        return $resolved;
    }

    protected function namedType(ReflectionNamedType $type): OpenApi\Type
    {
        if (($mapped = \Genesiscz\LaravelTypescriptApi\Scramble\ClassTypeMap::resolve($type->getName())) !== null) {
            return $mapped;
        }

        return match ($type->getName()) {
            'int' => new OpenApi\IntegerType,
            'float' => new OpenApi\NumberType,
            'string' => new OpenApi\StringType,
            'bool' => new OpenApi\BooleanType,
            'array' => new OpenApi\ArrayType,
            'mixed', 'object' => new OpenApi\UnknownType,
            // Reference extends Type — nested classes/enums route through
            // the full extension chain and land in components.
            default => class_exists($type->getName()) || enum_exists($type->getName())
                ? $this->openApiTransformer->transform(new ObjectType($type->getName()))
                : new OpenApi\UnknownType,
        };
    }
}
