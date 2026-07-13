<?php

namespace Genesiscz\LaravelTypescriptApi;

use Genesiscz\LaravelTypescriptApi\Docs\DocsGenerator;
use Genesiscz\LaravelTypescriptApi\Docs\PreviewEngine;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;
use Genesiscz\LaravelTypescriptApi\Pipeline\GeneratePipeline;

/**
 * Facade root. Everything is also reachable piecemeal via pipeline() for
 * apps composing their own command.
 */
class LaravelTypescriptApiManager
{
    public function __construct(
        protected GeneratePipeline $pipeline,
        protected DocsGenerator $docs,
    ) {}

    /** Full run: openapi → engines → emitters → format. */
    public function generate(?array $config = null): GenerateContext
    {
        return $this->pipeline->run($config);
    }

    /** The composable stages, for hand-rolled commands. */
    public function pipeline(): GeneratePipeline
    {
        return $this->pipeline;
    }

    /** Just the OpenAPI document (array), no TS generation. */
    public function openapi(?array $config = null): array
    {
        $context = $this->pipeline->newContext($config);
        $this->pipeline->openapi($context);

        return $context->openapi;
    }

    /** Static docs page for the current artifact. */
    public function generateDocs(?PreviewEngine $engine = null, ?string $artifactPath = null): string
    {
        $engine ??= config('typescript-api.docs.engine', PreviewEngine::Scalar);
        $artifactPath ??= config('typescript-api.output.path').'/openapi.json';

        return $this->docs->generate($engine, $artifactPath);
    }
}
