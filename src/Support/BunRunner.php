<?php

namespace Genesiscz\LaravelTypescriptApi\Support;

use Genesiscz\LaravelTypescriptApi\Exceptions\GenerationFailed;
use Symfony\Component\Process\Process;

class BunRunner
{
    public function __construct(
        protected string $bun = 'bunx',
        protected ?string $cwd = null,
        protected int $timeout = 300,
        protected array $env = [],
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            bun: $config['bun'] ?? 'bunx',
            cwd: $config['cwd'] ?? base_path(),
            timeout: $config['timeout'] ?? 300,
            env: $config['env'] ?? [],
        );
    }

    /**
     * Run a TS tool via bunx, e.g. run('@hey-api/openapi-ts', ['-f', $configPath]).
     */
    public function run(string $tool, array $arguments = []): string
    {
        $process = new Process(
            [$this->bun, $tool, ...$arguments],
            $this->cwd,
            array_merge(['CI' => '1'], $this->env),
            timeout: $this->timeout,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw GenerationFailed::process($tool, $process);
        }

        return $process->getOutput();
    }
}
