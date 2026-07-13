<?php

namespace Genesiscz\LaravelTypescriptApi\OpenApi;

/**
 * Appends collector-provided classes to components.schemas so DTOs no
 * endpoint references still reach the TypeScript output (shared types,
 * broadcast payloads — typescript-transformer's raison d'être, kept).
 *
 * TODO(scaffold): reuse Scramble's TypeTransformer to build each schema from
 * the class reflection instead of the placeholder below.
 */
class SchemaAppender
{
    /**
     * @param  array<string, mixed>  $document
     * @param  list<class-string>  $classes
     * @return array<string, mixed>
     */
    public function append(array $document, array $classes): array
    {
        foreach ($classes as $class) {
            $name = $this->schemaName($class);

            // Scramble names schemas by (deduped) class basename — treat a
            // basename hit as "already documented" too, or every referenced
            // DTO would get a second, placeholder copy under its FQCN name.
            if (isset($document['components']['schemas'][$name])
                || isset($document['components']['schemas'][class_basename($class)])) {
                continue;
            }

            // TODO(scaffold): build the real schema via extras' reflector +
            // the document's TypeTransformer. Until then SKIP — an empty
            // placeholder schema would flow into the engines and produce
            // garbage TS types for every unreferenced DTO.
            continue;
        }

        return $document;
    }

    protected function schemaName(string $class): string
    {
        return str_replace('\\', '.', ltrim($class, '\\'));
    }
}
