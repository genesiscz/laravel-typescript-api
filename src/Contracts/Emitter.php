<?php

namespace Genesiscz\LaravelTypescriptApi\Contracts;

use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;

interface Emitter
{
    /**
     * Emit additional TypeScript on top of engine output — named hooks,
     * prefetch/ensure helpers, enums, barrel exports. Runs after every
     * engine, before formatting.
     */
    public function emit(GenerateContext $context, array $options): void;
}
