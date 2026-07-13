<?php

namespace Genesiscz\LaravelTypescriptApi\Emit;

use Genesiscz\LaravelTypescriptApi\Contracts\Emitter;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;
use Illuminate\Support\Facades\File;

/**
 * Extends the engine-generated barrel (index.ts) with exports for
 * emitter-produced files (enums.ts, …) so consumers import everything
 * from one place. Creates the barrel if no engine wrote one.
 */
class IndexEmitter implements Emitter
{
    public function emit(GenerateContext $context, array $options): void
    {
        $indexPath = $context->outputPath.'/index.ts';
        $existing = File::exists($indexPath) ? File::get($indexPath) : '';

        $additions = '';

        // No default modules: enums.ts intentionally stays OUT of the barrel —
        // engine output (types.gen.ts) exports the same enum names and
        // re-exporting both would collide. Import '@/api/enums' directly.
        foreach ($options['exports'] ?? [] as $module) {
            if (! File::exists($context->outputPath.'/'.basename($module).'.ts')) {
                continue;
            }

            $line = "export * from \"{$module}\";";

            if (! str_contains($existing, $module)) {
                $additions .= $line."\n";
            }
        }

        if ($additions === '') {
            return;
        }

        File::put($indexPath, rtrim($existing)."\n".$additions);
        $context->trackFile($indexPath);
    }
}
