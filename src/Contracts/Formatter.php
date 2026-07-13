<?php

namespace Genesiscz\LaravelTypescriptApi\Contracts;

interface Formatter
{
    /** Format the generated output tree in place. */
    public function format(string $path, array $options): void;
}
