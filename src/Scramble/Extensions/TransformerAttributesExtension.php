<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble\Extensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Honors Spatie typescript-transformer attributes on laravel-data classes
 * inside the OpenAPI pipeline, so a codebase annotated for
 * typescript-transformer (à la ReservineBack) describes itself identically
 * through Scramble:
 *
 *   #[TypeScriptType(Foo::class.'[]')]     → overrides the property schema
 *   #[LiteralTypeScriptType('Record<...>')]→ mapped literal (best effort)
 *   #[Optional] (spatie/typescript-transformer) → property not required
 *
 * TODO(scaffold): implement — read attribute reflection off the Data class
 * properties and rewrite the produced schema (shouldHandle: ObjectType
 * subclassing Spatie\LaravelData\Data with any of the attributes present).
 */
class TransformerAttributesExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        // TODO(scaffold): ObjectType + Data subclass + transformer attributes present.
        return false;
    }

    public function toSchema(Type $type): ?OpenApiType
    {
        /** @var ObjectType $type */
        // TODO(scaffold): delegate to the default/extras schema, then patch
        // per-property overrides from the attributes.
        return null;
    }
}
