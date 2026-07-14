<?php

namespace Genesiscz\LaravelTypescriptApi;

use Dedoc\Scramble\Scramble;
use Genesiscz\LaravelTypescriptApi\Commands\DocsCommand;
use Genesiscz\LaravelTypescriptApi\Commands\GenerateCommand;
use Genesiscz\LaravelTypescriptApi\Docs\PreviewEngine;
use Genesiscz\LaravelTypescriptApi\Support\BunRunner;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTypescriptApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('typescript-api')
            ->hasConfigFile()
            ->hasViews('typescript-api')
            ->hasCommands([
                GenerateCommand::class,
                DocsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(LaravelTypescriptApiManager::class);

        $this->app->bind(
            BunRunner::class,
            fn () => BunRunner::fromConfig(config('typescript-api.runner', [])),
        );

        // Must happen in the register phase: scramble snapshots OPERATION
        // extensions from Scramble::$extensions at its own boot (the
        // withOperationTransformers mapper runs immediately), so a
        // generate-time registerExtension() is too late for them —
        // TypeToSchema extensions resolve lazily and wouldn't care.
        Scramble::registerExtensions(
            config('typescript-api.openapi.scramble.extensions', []),
        );
    }

    public function packageBooted(): void
    {
        $this->registerDocsRoute();
    }

    protected function registerDocsRoute(): void
    {
        $docs = config('typescript-api.docs', []);

        if (($docs['enabled'] ?? false) !== true) {
            return;
        }

        if (! app()->environment($docs['environments'] ?? ['local'])) {
            return;
        }

        Route::middleware($docs['middleware'] ?? ['web'])
            ->get($docs['route'] ?? '/docs/api', function () use ($docs) {
                $engine = $docs['engine'] ?? PreviewEngine::Scalar;
                $artifact = config('typescript-api.output.path').'/openapi.json';

                abort_unless(file_exists($artifact), 404, 'Run laravel-typescript-api:generate first.');

                return response(
                    app(Docs\DocsGenerator::class)->render($engine, url($docs['route'].'.json')),
                );
            })
            ->name('typescript-api.docs');

        Route::middleware($docs['middleware'] ?? ['web'])
            ->get(($docs['route'] ?? '/docs/api').'.json', function () {
                $artifact = config('typescript-api.output.path').'/openapi.json';

                abort_unless(file_exists($artifact), 404);

                return response()->file($artifact, ['Content-Type' => 'application/json']);
            })
            ->name('typescript-api.docs.json');
    }
}
