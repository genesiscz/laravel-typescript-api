<?php

namespace Genesiscz\LaravelTypescriptApi\Support;

use Genesiscz\LaravelTypescriptApi\Exceptions\GenerationFailed;
use Symfony\Component\Process\Process;

/**
 * Executes TS tooling through bun inside a package-managed workspace
 * (default: storage/typescript-api). The workspace holds a package.json
 * with the tools each pipeline stage declares — this is what makes peer
 * dependencies work (e.g. @hey-api/openapi-ts requires `typescript`,
 * which a bare `bunx` invocation cannot provide).
 */
class BunRunner
{
    public function __construct(
        protected string $bun = 'bun',
        protected ?string $workspace = null,
        protected int $timeout = 300,
        protected array $env = [],
        protected array $dependencyOverrides = [],
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            bun: $config['bun'] ?? 'bun',
            workspace: $config['workspace'] ?? storage_path('typescript-api'),
            timeout: $config['timeout'] ?? 300,
            env: $config['env'] ?? [],
            dependencyOverrides: $config['dependencies'] ?? [],
        );
    }

    /**
     * Ensure the workspace has the given npm dependencies installed,
     * then run a binary from its node_modules/.bin.
     *
     * @param  array<string, string>  $dependencies  package => version-range
     * @param  list<string>  $arguments
     */
    public function run(array $dependencies, string $binary, array $arguments = []): string
    {
        $this->ensureWorkspace($dependencies);

        return $this->execute(
            [$this->workspace.'/node_modules/.bin/'.$binary, ...$arguments],
            $this->workspace,
        );
    }

    /** @param array<string, string> $dependencies */
    protected function ensureWorkspace(array $dependencies): void
    {
        $dependencies = array_replace($dependencies, array_intersect_key($this->dependencyOverrides, $dependencies));

        if (! is_dir($this->workspace)) {
            mkdir($this->workspace, 0755, recursive: true);
        }

        $packageJsonPath = $this->workspace.'/package.json';
        $current = is_file($packageJsonPath)
            ? (json_decode((string) file_get_contents($packageJsonPath), true) ?: [])
            : ['name' => 'typescript-api-runner', 'private' => true, 'dependencies' => []];

        $missing = array_diff_key($dependencies, $current['dependencies'] ?? []);

        if ($missing === [] && is_dir($this->workspace.'/node_modules')) {
            return;
        }

        $current['dependencies'] = array_replace($current['dependencies'] ?? [], $dependencies);
        ksort($current['dependencies']);

        file_put_contents($packageJsonPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->execute([$this->bun, 'install'], $this->workspace);
    }

    /** @param list<string> $command */
    protected function execute(array $command, string $cwd): string
    {
        $process = new Process(
            $command,
            $cwd,
            array_merge(['CI' => '1'], $this->env),
            timeout: $this->timeout,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw GenerationFailed::process($command[0], $process);
        }

        return $process->getOutput();
    }
}
