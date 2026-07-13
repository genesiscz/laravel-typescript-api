<?php

namespace Genesiscz\LaravelTypescriptApi\Pipeline;

use Genesiscz\LaravelTypescriptApi\Contracts\Engine;
use Genesiscz\LaravelTypescriptApi\Support\BunRunner;

/**
 * Everything a pipeline stage needs: the resolved config, the OpenAPI
 * document (array form + artifact path) and the output tree. Passed through
 * collectors → engines → emitters → formatter.
 */
class GenerateContext
{
    /** @var array<string, mixed> OpenAPI document (decoded) */
    public array $openapi = [];

    /** Absolute path of the written openapi.json artifact. */
    public ?string $artifactPath = null;

    /** @var list<string> files written this run (manifest / clean policy) */
    public array $written = [];

    public function __construct(
        public readonly array $config,
        public readonly string $outputPath,
        public readonly BunRunner $runner,
    ) {}

    public function enginePath(Engine $engine): string
    {
        $dir = $this->config['engines'][$engine::class]['dir'] ?? $engine->name();

        return $dir === '' || $dir === null
            ? $this->outputPath
            : $this->outputPath.'/'.trim($dir, '/');
    }

    public function trackFile(string $path): void
    {
        $this->written[] = $path;
    }
}
