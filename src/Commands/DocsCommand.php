<?php

namespace Genesiscz\LaravelTypescriptApi\Commands;

use Genesiscz\LaravelTypescriptApi\Docs\PreviewEngine;
use Genesiscz\LaravelTypescriptApi\LaravelTypescriptApiManager;
use Illuminate\Console\Command;

class DocsCommand extends Command
{
    protected $signature = 'laravel-typescript-api:docs
        {--engine= : Preview engine (scalar, redoc, swagger-ui, elements)}';

    protected $description = 'Render a static API docs page from the generated openapi.json';

    public function handle(LaravelTypescriptApiManager $manager): int
    {
        $engine = $this->option('engine')
            ? PreviewEngine::from($this->option('engine'))
            : null;

        $path = $manager->generateDocs($engine);

        $this->components->info("Docs written to {$path}");

        return self::SUCCESS;
    }
}
