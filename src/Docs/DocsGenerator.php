<?php

namespace Genesiscz\LaravelTypescriptApi\Docs;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Renders the docs preview page for a given engine — either as a response
 * (route mode) or as a static HTML file next to the artifact (build mode):
 *
 *   LaravelTypescriptApi::generateDocs(engine: PreviewEngine::Redoc);
 */
class DocsGenerator
{
    /** Render the preview HTML for a spec URL/path. */
    public function render(PreviewEngine $engine, string $specUrl): string
    {
        return View::make($engine->view(), ['specUrl' => $specUrl])->render();
    }

    /** Write a static docs.html beside the artifact; returns the path. */
    public function generate(PreviewEngine $engine, string $artifactPath, ?string $outputPath = null): string
    {
        $outputPath ??= dirname($artifactPath).'/docs.html';

        File::put($outputPath, $this->render($engine, basename($artifactPath)));

        return $outputPath;
    }
}
