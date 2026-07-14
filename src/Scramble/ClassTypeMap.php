<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble;

use Dedoc\Scramble\Support\Generator\Types as OpenApi;

/**
 * Config-driven PHP class => TypeScript type mapping — the
 * typescript-transformer "transformers" concept. Value objects that
 * serialize to scalars (Brick\Money\Money => 'number') or shapes
 * (ArrayObject => 'Record<string, unknown>') would otherwise come out as
 * empty objects.
 *
 * Keywords map to real OpenAPI types; any other string becomes an
 * unconstrained schema carrying x-typescript-type, which the ambient
 * emitter reproduces verbatim.
 *
 * config: typescript-api.openapi.scramble.class_type_map
 */
class ClassTypeMap
{
    public static function resolve(string $className): ?OpenApi\Type
    {
        if (! class_exists($className) && ! enum_exists($className)) {
            return null;
        }

        foreach (config('typescript-api.openapi.scramble.class_type_map', []) as $class => $target) {
            if (is_a($className, $class, true)) {
                return static::fromKeyword($target);
            }
        }

        return null;
    }

    public static function fromKeyword(string $target): OpenApi\Type
    {
        return match ($target) {
            'number', 'float' => new OpenApi\NumberType,
            'integer', 'int' => new OpenApi\IntegerType,
            'string' => new OpenApi\StringType,
            'boolean', 'bool' => new OpenApi\BooleanType,
            'object' => new OpenApi\ObjectType,
            'any', 'mixed' => new OpenApi\MixedType,
            default => tap(new OpenApi\MixedType, fn ($type) => $type->setExtensionProperty('typescript-type', $target)),
        };
    }
}
