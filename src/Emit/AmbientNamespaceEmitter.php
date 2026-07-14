<?php

namespace Genesiscz\LaravelTypescriptApi\Emit;

use Genesiscz\LaravelTypescriptApi\Contracts\Emitter;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;
use Genesiscz\LaravelTypescriptApi\Support\PhpClassIndex;
use Illuminate\Support\Facades\File;
use Spatie\LaravelData\Contracts\BaseData;

/**
 * spatie/typescript-transformer-style ambient declarations (app.d.ts):
 *
 *     declare namespace App.Data {
 *         export type BranchData = { id: number; type: App.Enums.BranchType | null; };
 *     }
 *     declare namespace App.Enums {
 *         export const enum BranchType { Sport = 'sport', ... }
 *     }
 *
 * Every OpenAPI component schema is placed back into its original PHP
 * namespace (dotted schema names are already qualified — scramble's
 * basename-collision naming; plain names resolve via PhpClassIndex over
 * `namespaces`). Enums become `export const enum` with PHP case names
 * (x-enum-varnames). Drop-in replacement for a typescript-transformer
 * generated app.d.ts — frontends keep using `App.Data.X` with no imports.
 *
 * Cross-namespace references are emitted as the SHORTEST suffix that
 * resolves correctly under TypeScript's outward namespace lookup. That
 * matters: a PHP namespace like App\Http\Requests\App shadows the root
 * `App` for every sibling, so a naive `App.Enums.X` reference breaks —
 * `Enums.X` (resolved through the ancestor scope) does not.
 *
 * options:
 *  - file: output filename (default 'app.d.ts')
 *  - path: output DIRECTORY (default output.path; e.g. resource_path('types'))
 *  - namespaces: PHP roots to resolve against (default ['App\\'])
 *  - fallback_namespace: dotted namespace for schemas with no PHP match
 *    (default 'App'); set false to skip unmatched schemas entirely
 *  - declaration: 'type' | 'interface' (default 'type')
 *  - enum_import: module specifier (e.g. './enums') — enum references render
 *    as import('./enums').X (the RUNTIME enum emitted by EnumsEmitter)
 *    instead of the ambient const enum, so frontend comparisons against
 *    imported enum values type-check (enums are nominal in TS). Default null.
 *  - exclude_namespaces: dotted prefixes to skip in the output while keeping
 *    references to them fully qualified — for namespaces another generator
 *    owns (e.g. ['App.Models'] when models.d.ts is generated separately).
 */
class AmbientNamespaceEmitter implements Emitter
{
    /** @var array<string, string> schema name => fully qualified dotted TS name */
    protected array $qualified = [];

    /** @var array<string, true> every declared namespace path, prefixes included */
    protected array $namespaceSet = [];

    /** @var array<string, array<string, true>> namespace => direct member names (types + child namespaces) */
    protected array $members = [];

    /** @var array<string, true> schema names that are enums */
    protected array $enumSchemas = [];

    /** Module specifier for enum references (e.g. './enums'), or null. */
    protected ?string $enumImport = null;

    public function emit(GenerateContext $context, array $options): void
    {
        $schemas = $context->openapi['components']['schemas'] ?? [];

        if ($schemas === []) {
            return;
        }

        // Scramble's document contains stdClass nodes (empty objects) —
        // normalize the whole subtree to arrays before walking it.
        $schemas = json_decode(json_encode($schemas), true);

        $index = PhpClassIndex::buildAll(
            $options['namespaces'] ?? ['App\\'],
            array_keys($schemas),
        );

        // First pass: place every schema (namespace + member name) and build
        // the lookup structures suffix-reference resolution needs.
        $placement = [];

        foreach (array_keys($schemas) as $name) {
            if (str_contains($name, '.')) {
                // Already qualified: scramble dedupes basename collisions
                // with dotted names (App.Data.Branch.BranchData).
                $namespace = substr($name, 0, strrpos($name, '.'));
                $member = substr($name, strrpos($name, '.') + 1);
            } elseif (($fqcn = $this->pickCandidate($index[$name] ?? [], $schemas)) !== null) {
                $namespace = str_replace('\\', '.', substr($fqcn, 0, strrpos($fqcn, '\\')));
                $member = $name;
            } elseif (($parent = $this->longestPrefixCandidate($name, $index, $schemas)) !== null) {
                // Scramble-synthesized child type (OpeningHoursDataDay from
                // OpeningHoursData) — sits next to its parent class, exactly
                // where typescript-transformer put it.
                $namespace = str_replace('\\', '.', substr($parent, 0, strrpos($parent, '\\')));
                $member = $name;
            } elseif (($options['fallback_namespace'] ?? 'App') === false) {
                continue;
            } else {
                $namespace = $options['fallback_namespace'] ?? 'App';
                $member = $name;
            }

            $placement[$name] = [$namespace, $member];
            $this->qualified[$name] = $namespace.'.'.$member;

            $this->members[$namespace][$member] = true;

            $segments = explode('.', $namespace);

            for ($i = 1; $i <= count($segments); $i++) {
                $path = implode('.', array_slice($segments, 0, $i));
                $this->namespaceSet[$path] = true;

                if ($i > 1) {
                    $this->members[implode('.', array_slice($segments, 0, $i - 1))][$segments[$i - 1]] = true;
                }
            }
        }

        $this->enumImport = $options['enum_import'] ?? null;

        foreach ($schemas as $name => $schema) {
            if (is_array($schema) && isset($schema['enum'])) {
                $this->enumSchemas[$name] = true;
            }
        }

        $declaration = $options['declaration'] ?? 'type';
        $excluded = $options['exclude_namespaces'] ?? [];
        $blocks = [];

        foreach ($schemas as $name => $schema) {
            if (! isset($placement[$name])) {
                continue;
            }

            [$namespace, $member] = $placement[$name];

            // Excluded namespaces stay OUT of the output (another generator
            // owns them — e.g. App.Models via models.d.ts) but references
            // to them remain fully qualified against that other file.
            foreach ($excluded as $prefix) {
                if ($namespace === $prefix || str_starts_with($namespace, $prefix.'.')) {
                    continue 2;
                }
            }

            $blocks[$namespace][$member] = is_array($schema) && isset($schema['enum'])
                ? $this->renderConstEnum($member, $schema)
                : $this->renderType($member, $schema, $namespace, $declaration);
        }

        ksort($blocks);

        $out = "// Generated by laravel-typescript-api — do not edit.\n";

        foreach ($blocks as $namespace => $memberBlocks) {
            ksort($memberBlocks);
            $out .= "declare namespace {$namespace} {\n".implode('', $memberBlocks)."}\n";
        }

        $dir = $options['path'] ?? $context->outputPath;
        File::ensureDirectoryExists($dir);

        $path = rtrim($dir, '/').'/'.($options['file'] ?? 'app.d.ts');
        File::put($path, $out);
        $context->trackFile($path);
    }

    /**
     * A plain schema name is ambiguous when several classes share the
     * basename. Scramble gives dotted names to all but ONE of the colliding
     * classes — so the right candidate is the one whose own dotted form is
     * NOT already a schema.
     *
     * @param  list<class-string>  $candidates
     * @param  array<string, mixed>  $schemas
     * @return class-string|null
     */
    protected function pickCandidate(array $candidates, array $schemas): ?string
    {
        foreach ($candidates as $candidate) {
            if (! isset($schemas[str_replace('\\', '.', $candidate)])) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * The class whose basename is the LONGEST strict prefix of $name —
     * parent of a scramble-synthesized child schema name.
     *
     * @param  array<string, non-empty-list<class-string>>  $index
     * @param  array<string, mixed>  $schemas
     * @return class-string|null
     */
    protected function longestPrefixCandidate(string $name, array $index, array $schemas): ?string
    {
        $best = null;

        foreach ($index as $basename => $candidates) {
            // Synthesized children only spawn from Data parents — without
            // this constraint App\Models\Employee would capture
            // EmployeeMatchData.
            if ($basename === $name
                || ! str_starts_with($name, $basename)
                || ! is_a($candidates[0], BaseData::class, true)) {
                continue;
            }

            if ($best === null || strlen($basename) > strlen($best)) {
                $best = $basename;
            }
        }

        return $best === null ? null : $this->pickCandidate($index[$best], $schemas);
    }

    protected function renderType(string $name, array|bool $schema, string $namespace, string $declaration): string
    {
        $type = $this->tsType($schema, $namespace, 1);

        if ($declaration === 'interface' && str_starts_with($type, '{')) {
            return "\texport interface {$name} {$type}\n";
        }

        return "\texport type {$name} = {$type};\n";
    }

    protected function renderConstEnum(string $name, array $schema): string
    {
        $values = $schema['enum'];
        $names = $schema['x-enum-varnames']
            ?? $schema['x-enumNames']
            ?? array_map(fn ($value) => (string) $value, $values);

        $out = "\texport const enum {$name} {\n";

        foreach (array_combine($names, $values) as $member => $value) {
            $member = preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', (string) $member)
                ? $member
                : "'".addslashes((string) $member)."'";

            $out .= "\t\t{$member} = ".$this->literal($value).",\n";
        }

        return $out."\t}\n";
    }

    /**
     * Shortest reference to $target (fully qualified dotted name) that
     * TypeScript resolves correctly FROM inside $currentNamespace.
     *
     * TS resolves the first segment of a qualified name by walking scopes
     * outward (current namespace, its ancestors, global). A sibling
     * namespace whose name repeats a root segment (App.Http.Requests.App)
     * shadows the root, so the full path is not always usable — pick the
     * longest suffix whose first segment resolves to the right place.
     */
    protected function reference(string $target, string $currentNamespace): string
    {
        $segments = explode('.', $target);

        for ($i = count($segments) - 1; $i >= 0; $i--) {
            $prefix = implode('.', array_slice($segments, 0, $i + 1));

            if ($this->lookup($segments[$i], $currentNamespace) === $prefix) {
                return implode('.', array_slice($segments, $i));
            }
        }

        return $target;
    }

    /** Simulate TS outward scope lookup of $name from inside $scope. */
    protected function lookup(string $name, string $scope): ?string
    {
        $segments = explode('.', $scope);

        for ($i = count($segments); $i >= 0; $i--) {
            $path = implode('.', array_slice($segments, 0, $i));

            if ($path === '') {
                return isset($this->namespaceSet[$name]) ? $name : null;
            }

            if (isset($this->members[$path][$name])) {
                return $path.'.'.$name;
            }
        }

        return null;
    }

    /** OpenAPI (3.1) schema => TypeScript type expression. */
    protected function tsType(array|bool $schema, string $namespace, int $depth): string
    {
        if (! is_array($schema) || $schema === []) {
            return 'any';
        }

        if (isset($schema['$ref'])) {
            $name = basename($schema['$ref']);

            // Enum references can point at the RUNTIME enum module
            // (enums.ts) instead of the ambient const enum — property types
            // then nominally match values the frontend imports, so
            // `data.status === Status.Confirmed` comparisons type-check.
            if ($this->enumImport !== null && isset($this->enumSchemas[$name]) && ! str_contains($name, '.')) {
                return "import('{$this->enumImport}').{$name}";
            }

            $target = $this->qualified[$name] ?? null;

            return $target === null ? $name : $this->reference($target, $namespace);
        }

        if (isset($schema['allOf'])) {
            $parts = array_values(array_filter(array_map(
                fn ($part) => $this->tsType($part, $namespace, $depth),
                $schema['allOf'],
            ), fn ($part) => $part !== 'any'));

            return $parts === [] ? 'any' : implode(' & ', $parts);
        }

        foreach (['oneOf', 'anyOf'] as $union) {
            if (isset($schema[$union])) {
                $parts = array_unique(array_map(
                    fn ($part) => $this->tsType($part, $namespace, $depth),
                    $schema[$union],
                ));

                return implode(' | ', $parts);
            }
        }

        if (isset($schema['enum'])) {
            return implode(' | ', array_map(fn ($value) => $this->literal($value), $schema['enum']));
        }

        if (array_key_exists('const', $schema)) {
            return $this->literal($schema['const']);
        }

        // 3.1 nullable: type: ['string', 'null']
        if (isset($schema['type']) && is_array($schema['type'])) {
            $parts = array_map(
                fn ($type) => $this->tsType(['type' => $type] + $schema, $namespace, $depth),
                $schema['type'],
            );

            return implode(' | ', array_unique($parts));
        }

        return match ($schema['type'] ?? null) {
            'string' => 'string',
            'integer', 'number' => 'number',
            'boolean' => 'boolean',
            'null' => 'null',
            'array' => 'Array<'.$this->tsType($schema['items'] ?? true, $namespace, $depth).'>',
            'object' => $this->objectType($schema, $namespace, $depth),
            default => isset($schema['properties']) ? $this->objectType($schema, $namespace, $depth) : 'any',
        };
    }

    protected function objectType(array $schema, string $namespace, int $depth): string
    {
        $properties = $schema['properties'] ?? [];
        $required = array_flip($schema['required'] ?? []);
        $additional = $schema['additionalProperties'] ?? false;

        if ($properties === [] && $additional === false) {
            return array_key_exists('properties', $schema) ? '{}' : 'object';
        }

        $indent = str_repeat("\t", $depth + 1);
        $lines = [];

        foreach ($properties as $key => $property) {
            $optional = isset($required[$key]) ? '' : '?';
            $key = preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', (string) $key)
                ? $key
                : "'".addslashes((string) $key)."'";

            $lines[] = "{$indent}{$key}{$optional}: ".$this->tsType($property, $namespace, $depth + 1).';';
        }

        if ($additional !== false) {
            $lines[] = "{$indent}[key: string]: ".$this->tsType($additional === true ? true : $additional, $namespace, $depth + 1).';';
        }

        return "{\n".implode("\n", $lines)."\n".str_repeat("\t", $depth).'}';
    }

    protected function literal(mixed $value): string
    {
        return match (true) {
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => "'".addslashes((string) $value)."'",
        };
    }
}
