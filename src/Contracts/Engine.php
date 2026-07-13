<?php

namespace Genesiscz\LaravelTypescriptApi\Contracts;

use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;

interface Engine
{
    /** Short identifier used in output paths and logs (e.g. "hey-api"). */
    public function name(): string;

    /**
     * Consume the OpenAPI artifact and produce TypeScript into
     * $context->enginePath($this). Implementations write their own engine
     * config file and shell out via the BunRunner.
     */
    public function generate(GenerateContext $context, array $options): void;
}
