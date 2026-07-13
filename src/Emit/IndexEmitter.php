<?php

namespace Genesiscz\LaravelTypescriptApi\Emit;

use Genesiscz\LaravelTypescriptApi\Contracts\Emitter;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;

/**
 * Barrel index.ts re-exporting the whole generated surface (types, sdk,
 * hooks, enums) so consumers import from one place.
 *
 * TODO(scaffold): implement — walk context->written, group by directory,
 * write index.ts per level.
 */
class IndexEmitter implements Emitter
{
    public function emit(GenerateContext $context, array $options): void
    {
        // TODO(scaffold)
    }
}
