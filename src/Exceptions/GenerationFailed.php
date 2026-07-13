<?php

namespace Genesiscz\LaravelTypescriptApi\Exceptions;

use RuntimeException;
use Symfony\Component\Process\Process;

class GenerationFailed extends RuntimeException
{
    public static function process(string $tool, Process $process): self
    {
        return new self(sprintf(
            "%s failed (exit %d):\n%s\n%s",
            $tool,
            $process->getExitCode() ?? -1,
            $process->getOutput(),
            $process->getErrorOutput(),
        ));
    }
}
