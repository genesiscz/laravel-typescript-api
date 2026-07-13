<?php

namespace Genesiscz\LaravelTypescriptApi\Commands;

use Genesiscz\LaravelTypescriptApi\LaravelTypescriptApiManager;
use Illuminate\Console\Command;

class GenerateCommand extends Command
{
    protected $signature = 'laravel-typescript-api:generate
        {--openapi-only : Build and write openapi.json, skip TS engines}
        {--engine=* : Limit to specific engine class basenames (HeyApiEngine, OrvalEngine)}
        {--no-format : Skip the formatting stage}';

    protected $description = 'Generate the TypeScript API client (types, TanStack Query bindings, enums) from your controllers and laravel-data DTOs';

    public function handle(LaravelTypescriptApiManager $manager): int
    {
        $config = config('typescript-api');

        if ($this->option('no-format')) {
            $config['format']['formatter'] = null;
        }

        if ($engines = $this->option('engine')) {
            foreach ($config['engines'] as $class => $options) {
                $config['engines'][$class]['enabled'] = in_array(class_basename($class), $engines, true);
            }
        }

        if ($this->option('openapi-only')) {
            $config['engines'] = [];
            $config['emit'] = [];
        }

        $this->components->info('Generating TypeScript API client…');

        $context = $manager->generate($config);

        $this->components->twoColumnDetail('OpenAPI artifact', $context->artifactPath ?? '(not written)');
        $this->components->twoColumnDetail('Output', $context->outputPath);
        $this->components->twoColumnDetail('Files written', (string) count($context->written));

        if ($context->errors !== []) {
            $this->components->warn(count($context->errors).' route(s) could not be analyzed — their schemas may be incomplete:');

            foreach (array_slice($context->errors, 0, 10) as $error) {
                $this->components->bulletList([str($error)->limit(160)->toString()]);
            }
        }

        return self::SUCCESS;
    }
}
