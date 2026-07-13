<?php

namespace Genesiscz\LaravelTypescriptApi\Format;

use Genesiscz\LaravelTypescriptApi\Contracts\Formatter;
use Genesiscz\LaravelTypescriptApi\Support\BunRunner;

class BiomeFormatter implements Formatter
{
    public function __construct(protected BunRunner $runner) {}

    public function format(string $path, array $options): void
    {
        $this->runner->run(
            ['@biomejs/biome' => '^2'],
            'biome',
            ['format', '--write', $path, ...($options['args'] ?? [])],
        );
    }
}
