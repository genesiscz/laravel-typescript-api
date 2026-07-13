<?php

namespace Genesiscz\LaravelTypescriptApi\Format;

use Genesiscz\LaravelTypescriptApi\Contracts\Formatter;
use Genesiscz\LaravelTypescriptApi\Support\BunRunner;

class PrettierFormatter implements Formatter
{
    public function __construct(protected BunRunner $runner) {}

    public function format(string $path, array $options): void
    {
        $this->runner->run(
            ['prettier' => '^3'],
            'prettier',
            ['--write', $path.'/**/*.ts', ...($options['args'] ?? [])],
        );
    }
}
