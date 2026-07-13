<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use PawelJadanowski\ScrambleExtras\LaravelDataReflector;
use PawelJadanowski\ScrambleExtras\SchemaCache;
use ReflectionClass;
use ReflectionProperty;
use Spatie\TypeScriptTransformer\Attributes\Optional as TransformerOptional;

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

        return $schema;
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
