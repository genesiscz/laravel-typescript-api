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

            if (isset($document['components']['schemas'][$name])) {
                continue; // already referenced by an endpoint
            }

            // TODO(scaffold): $schema = $this->typeTransformer->transform($class);
            $document['components']['schemas'][$name] = [
                'type' => 'object',
                'description' => "TODO: schema for {$class} (collector-appended)",
            ];
        }

        return $document;
    }

    protected function schemaName(string $class): string
    {
        return str_replace('\\', '.', ltrim($class, '\\'));
    }
}
