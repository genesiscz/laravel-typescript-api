<?php

namespace Genesiscz\LaravelTypescriptApi\Emit;

use Genesiscz\LaravelTypescriptApi\Contracts\Emitter;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;

/**
 * Authoritative enums.ts from the OpenAPI document's enum schemas —
 * x-enum-varnames (stamped by EnumVarnamesExtension) become member names,
 * so output matches PHP case names verbatim (ReservineBack enums.ts parity).
 *
 * options.style: 'enum' (real TS enum) | 'const' (as-const object) | 'union'.
 *
 * TODO(scaffold): implement — scan components.schemas for enum +
 * x-enum-varnames, render options.file.
 */
class EnumsEmitter implements Emitter
{
    public function emit(GenerateContext $context, array $options): void
    {
        // TODO(scaffold)
    }
}
