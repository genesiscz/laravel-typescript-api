<?php

namespace Genesiscz\LaravelTypescriptApi\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;

/**
 * Materializes collector-provided classes into components.schemas through
 * the generation run's OWN TypeTransformer — the same extension chain
 * (extras + TransformerAttributesExtension) routes referenced, so DTOs no
 * endpoint mentions still land in the document with full property schemas,
 * nested references included (typescript-transformer's collector idea,
 * OpenAPI flavored).
 *
 * Registered as a Scramble document transformer; runs after all routes are
 * analyzed, so anything a route already referenced is skipped by the
 * reference registry.
 */
class CollectedSchemasTransformer implements DocumentTransformer
{
    /** @var list<class-string> set by the pipeline before generation */
    public static array $classes = [];

    public function __construct(protected TypeTransformer $typeTransformer) {}

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach (static::$classes as $class) {
            // transform() registers the component schema (and every nested
            // reference) as a side effect; already-known references are
            // returned from the registry without re-analysis.
            $this->typeTransformer->transform(new ObjectType(ltrim($class, '\\')));
        }
    }
}
