<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble\Extensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Stamps `x-enum-varnames` (PHP enum case names, verbatim) onto every enum
 * schema. Both Orval and hey-api read it to generate real named TS enum
 * members instead of value-derived heuristics — the fidelity bridge that
 * makes generated enums match ReservineBack's enums.ts.
 *
 * TODO(scaffold): implement — shouldHandle on enum-backed types, decorate
 * the schema produced by Scramble's own EnumToSchema with the case names
 * (native enums + bensampo/laravel-enum).
 */
class EnumVarnamesExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        // TODO(scaffold)
        return false;
    }

    public function toSchema(Type $type): ?OpenApiType
    {
        // TODO(scaffold)
        return null;
    }
}
