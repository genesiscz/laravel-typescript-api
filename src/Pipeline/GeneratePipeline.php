<?php

namespace Genesiscz\LaravelTypescriptApi\Pipeline;

use Genesiscz\LaravelTypescriptApi\Contracts\Collector;
use Genesiscz\LaravelTypescriptApi\Contracts\Emitter;
use Genesiscz\LaravelTypescriptApi\Contracts\Engine;
use Genesiscz\LaravelTypescriptApi\Contracts\Formatter;
use Genesiscz\LaravelTypescriptApi\OpenApi\OpenApiBuilder;
use Genesiscz\LaravelTypescriptApi\Support\BunRunner;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\File;

/**
 * The whole generate flow, stage by stage. Each stage is also public so an
 * app can compose its own command (à la ReservineBack app:types) from the
 * pieces it wants: openapi() → engines() → emitters() → format().
 */
class GeneratePipeline
{
    public function __construct(
        protected Container $container,
        protected OpenApiBuilder $openApiBuilder,
    ) {}

    public function run(?array $config = null): GenerateContext
    {
        $context = $this->newContext($config);

        $this->openapi($context);
        $this->engines($context);
        $this->emitters($context);
        $this->format($context);
        $this->writeManifest($context);

        return $context;
    }

    public function newContext(?array $config = null): GenerateContext
    {
        $config ??= config('typescript-api');
        $outputPath = $config['output']['path'];

        if (($config['output']['clean'] ?? false) === true) {
            $this->cleanFromManifest($outputPath);
        }

        File::ensureDirectoryExists($outputPath);

        return new GenerateContext(
            config: $config,
            outputPath: $outputPath,
            runner: BunRunner::fromConfig($config['runner'] ?? []),
        );
    }

    /** Stage 1+2: collect extra schemas, build the OpenAPI document, write artifact. */
    public function openapi(GenerateContext $context): void
    {
        $extraSchemas = [];

        foreach ($context->config['openapi']['collectors'] ?? [] as $class => $options) {
            [$class, $options] = $this->normalizeEntry($class, $options);

            /** @var Collector $collector */
            $collector = $this->container->make($class);

            foreach ($collector->collect($options) as $schemaClass) {
                $extraSchemas[] = $schemaClass;
            }
        }

        $context->openapi = $this->openApiBuilder->build($context->config['openapi'] ?? [], $extraSchemas);
        $context->errors = $this->openApiBuilder->errors;

        if ($context->config['openapi']['artifact']['write'] ?? true) {
            $path = $context->config['openapi']['artifact']['path']
                ?? $context->outputPath.'/openapi.json';

            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode(
                $context->openapi,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            $context->artifactPath = $path;
            $context->trackFile($path);
        }
    }

    /** Stage 3: run every enabled engine against the artifact. */
    public function engines(GenerateContext $context): void
    {
        foreach ($context->config['engines'] ?? [] as $class => $options) {
            [$class, $options] = $this->normalizeEntry($class, $options);

            if (($options['enabled'] ?? false) !== true) {
                continue;
            }

            /** @var Engine $engine */
            $engine = $this->container->make($class);
            $engine->generate($context, $options);
        }
    }

    /** Stage 4: our own TypeScript on top (hooks, prefetch, enums, barrels). */
    public function emitters(GenerateContext $context): void
    {
        foreach ($context->config['emit'] ?? [] as $class => $options) {
            [$class, $options] = $this->normalizeEntry($class, $options);

            if (($options['enabled'] ?? true) !== true) {
                continue;
            }

            /** @var Emitter $emitter */
            $emitter = $this->container->make($class);
            $emitter->emit($context, $options);
        }
    }

    /** Stage 5: prettier / biome / custom over the final tree. */
    public function format(GenerateContext $context): void
    {
        $class = $context->config['format']['formatter'] ?? null;

        if ($class === null) {
            return;
        }

        /** @var Formatter $formatter */
        $formatter = $this->container->make($class);
        $formatter->format($context->outputPath, $context->config['format']['options'] ?? []);
    }

    protected function writeManifest(GenerateContext $context): void
    {
        File::put(
            $context->outputPath.'/.manifest.json',
            json_encode(['files' => $context->written], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /** Only delete what a previous run of OURS produced. */
    protected function cleanFromManifest(string $outputPath): void
    {
        $manifest = $outputPath.'/.manifest.json';

        if (! File::exists($manifest)) {
            return;
        }

        $files = json_decode(File::get($manifest), true)['files'] ?? [];

        foreach ($files as $file) {
            File::delete($file);
        }
    }

    /**
     * Config entries may be `Class::class` (int key, no options) or
     * `Class::class => [options]`.
     *
     * @return array{0: class-string, 1: array}
     */
    protected function normalizeEntry(int|string $key, mixed $value): array
    {
        return is_int($key) ? [$value, []] : [$key, (array) $value];
    }
}
