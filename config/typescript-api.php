<?php

use Genesiscz\LaravelTypescriptApi\Collectors\DataNamespaceCollector;
use Genesiscz\LaravelTypescriptApi\Docs\PreviewEngine;
use Genesiscz\LaravelTypescriptApi\Emit\AmbientNamespaceEmitter;
use Genesiscz\LaravelTypescriptApi\Emit\EnumsEmitter;
use Genesiscz\LaravelTypescriptApi\Emit\HooksEmitter;
use Genesiscz\LaravelTypescriptApi\Emit\IndexEmitter;
use Genesiscz\LaravelTypescriptApi\Emit\PrefetchEmitter;
use Genesiscz\LaravelTypescriptApi\Engines\HeyApiEngine;
use Genesiscz\LaravelTypescriptApi\Engines\OrvalEngine;
use Genesiscz\LaravelTypescriptApi\Format\BiomeFormatter;
use Genesiscz\LaravelTypescriptApi\Scramble\Extensions\BenSampoEnumExtension;
use Genesiscz\LaravelTypescriptApi\Scramble\Extensions\ResponseEnvelopeExtension;
use Genesiscz\LaravelTypescriptApi\Scramble\Extensions\TransformerAttributesExtension;

return [

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    | Where generated TypeScript lands. `clean` wipes the tree before each
    | run (a .manifest.json tracks generated files so only OUR files die).
    */
    'output' => [
        'path' => env('TYPESCRIPT_API_OUTPUT', resource_path('ts/api')),
        'clean' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI stage (Scramble)
    |--------------------------------------------------------------------------
    | The middle artifact every engine consumes. Scramble does route + type
    | inference; scramble-extras adds spatie/laravel-data depth; our
    | extensions add typescript-transformer attribute support, enum case
    | names and response-envelope awareness.
    |
    | `collectors` force schemas into the document that no endpoint
    | references (shared DTOs, websocket payloads) — same idea as
    | typescript-transformer's collectors, OpenAPI flavored.
    */
    'openapi' => [
        // 'scramble' (live inference) | 'file' (pre-built spec) | callable
        'source' => 'scramble',

        // Used when source === 'file'
        'file' => null,

        'scramble' => [
            // Scramble API name (multi-doc setups); null = default
            'api' => 'default',

            // Our + your Dedoc\Scramble extensions (TypeToSchema / Infer).
            // Order matters. Class-string, or [class-string => options].
            'extensions' => [
                TransformerAttributesExtension::class,
                ResponseEnvelopeExtension::class,
                BenSampoEnumExtension::class,
            ],

            // Merged into config('scramble') for the duration of a build.
            // enum_cases_names_strategy 'varnames' → x-enum-varnames with PHP
            // case names, which both engines turn into real named TS enums.
            'config' => [
                'enum_cases_names_strategy' => 'varnames',
            ],

            // Data classes under these namespaces build as INPUT schemas:
            // #[Hidden] props (never serialized out) still appear — the
            // frontend SENDS them; #[Computed] props are dropped.
            'input_namespaces' => [
                'App\\Http\\Requests\\',
            ],

            // PHP class => TS type for value objects that serialize to
            // scalars/shapes (typescript-transformer's "transformers").
            // Keywords (number/string/...) map to real schema types; any
            // other string is emitted verbatim in the ambient app.d.ts.
            'class_type_map' => [
                // \Brick\Money\Money::class => 'number',
                // \Illuminate\Database\Eloquent\Casts\ArrayObject::class => 'Record<string, unknown>',
            ],
        ],

        'collectors' => [
            DataNamespaceCollector::class => [
                'namespaces' => [
                    'App\\Data',
                    'App\\Http\\Requests',
                    'App\\Http\\Responses',
                ],
                // Skip abstract classes / traits automatically; add your own:
                'except' => [],
            ],
            // Classes anywhere that opt in with #[TypeScript] — same
            // convention spatie/typescript-transformer used.
            // \Genesiscz\LaravelTypescriptApi\Collectors\TypeScriptAttributeCollector::class => [
            //     'namespaces' => ['App\\'],
            // ],
        ],

        // Unwrap a response envelope so client types match what callers see
        // AFTER your mutator/interceptor unwraps, e.g. api-response-builder's
        // { success, code, data } => 'data'. null = no envelope.
        'envelope' => [
            'unwrap' => null,           // e.g. 'data'
            'document_full' => true,    // keep full envelope in docs

            // Where `- Response: Foo` docblock names are resolved.
            'response_namespaces' => [
                'App\\Http\\Responses\\',
                'App\\Data\\',
            ],
        ],

        // Write the artifact next to generated TS (CI contract diffing, docs)
        'artifact' => [
            'write' => true,
            'path' => null,             // default: <output>/openapi.json
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Engines — OpenAPI → TypeScript
    |--------------------------------------------------------------------------
    | Every enabled engine runs, each into its own subdirectory. Ship one,
    | ship both — they consume the same openapi.json.
    |
    | Each engine has a few first-class keys, and `passthrough`: a raw array
    | merged VERBATIM into the generated engine config (orval.config.ts /
    | openapi-ts.config.ts). New upstream feature? Use passthrough today —
    | no package update, no BC surface.
    */
    'engines' => [

        HeyApiEngine::class => [
            'enabled' => true,
            'dir' => '',                // subdir under output.path ('' = root)
            'plugins' => [
                '@hey-api/typescript' => [
                    // real named TS enums from x-enum-varnames (PHP case names)
                    'enums' => ['mode' => 'typescript', 'case' => 'preserve'],
                ],
                '@hey-api/sdk',
                '@tanstack/react-query' => [
                    // queryOptions AND named hooks — both styles served
                    'useQuery' => true,
                    'useMutation' => true,
                    'infiniteQueryOptions' => true,
                ],
                // 'zod',
                // ['name' => '@hey-api/transformers', 'dates' => true],
                // '@tanstack/angular-query-experimental',
            ],
            'client' => '@hey-api/client-fetch',
            'passthrough' => [],
        ],

        OrvalEngine::class => [
            'enabled' => false,
            'dir' => 'orval',
            'client' => 'react-query',  // react-query | angular-query | zod | ...
            'httpClient' => 'fetch',
            'mode' => 'tags-split',
            'mock' => false,            // MSW mocks
            'mutator' => null,          // ['path' => ..., 'name' => ...]
            'query' => [
                'useSuspenseQuery' => true,
                'useInfinite' => false,
            ],
            'passthrough' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Emitters — our own TypeScript on top of engine output
    |--------------------------------------------------------------------------
    | Composable, ordered, class-string keyed (roll your own via
    | Contracts\Emitter). The 7nohe-inspired layer lives here: named hooks
    | wrapping hey-api queryOptions, prefetch/ensure helpers, real TS enums.
    */
    'emit' => [
        // hey-api's @tanstack/react-query plugin emits named hooks natively
        // (useQuery/useMutation flags above) — this emitter exists for
        // engines/situations without native hook emission.
        HooksEmitter::class => [
            'enabled' => false,
        ],
        // prefetchX()/ensureXData() SSR helpers (7nohe pattern) — planned as
        // a hey-api custom plugin so symbol names always match the SDK.
        PrefetchEmitter::class => [
            'enabled' => false,
            'ensure' => true,
        ],
        EnumsEmitter::class => [
            'enabled' => true,
            'style' => 'enum',          // 'enum' | 'const' | 'union'
            'file' => 'enums.ts',
            'path' => null,             // output DIRECTORY; null = output.path
        ],
        // typescript-transformer-style ambient app.d.ts — schemas placed
        // back into their PHP namespaces (`declare namespace App.Data`),
        // enums as `export const enum` in App.Enums, refs fully qualified.
        // Drop-in replacement for a spatie/typescript-transformer app.d.ts:
        // point `path` at resource_path('types') and frontends keep using
        // App.Data.X globally, no imports.
        AmbientNamespaceEmitter::class => [
            'enabled' => false,
            'file' => 'app.d.ts',
            'path' => null,             // e.g. resource_path('types')
            'namespaces' => ['App\\'],  // PHP roots resolved via PSR-4 file scan
            'fallback_namespace' => 'App', // unmatched schemas; false = skip
            'declaration' => 'type',    // 'type' | 'interface'
            // './enums' => enum refs render as import('./enums').X (the
            // runtime enum from EnumsEmitter) so frontend value comparisons
            // type-check; null = reference the ambient const enums.
            'enum_import' => null,
            // Namespaces another generator owns (refs stay qualified):
            'exclude_namespaces' => [],  // e.g. ['App.Models'] with models.d.ts
        ],
        IndexEmitter::class => [
            'enabled' => true,          // barrel exports (adds ./enums etc.)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    | Runs over the final output tree. BiomeFormatter | PrettierFormatter |
    | null | your own Contracts\Formatter.
    */
    'format' => [
        'formatter' => BiomeFormatter::class,
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runner — how TS tooling executes
    |--------------------------------------------------------------------------
    */
    'runner' => [
        'bun' => env('TYPESCRIPT_API_BUN', 'bun'),

        // Package-managed bun workspace holding the TS tooling (so peer
        // dependencies like typescript for hey-api actually resolve).
        'workspace' => null,            // null = storage_path('typescript-api')

        // Pin/override tool versions, e.g. ['@hey-api/openapi-ts' => '0.99.0']
        'dependencies' => [],

        'timeout' => 300,
        'env' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Docs — live preview of the same openapi.json
    |--------------------------------------------------------------------------
    | LaravelTypescriptApi::generateDocs(engine: PreviewEngine::Redoc) or the
    | registered route. Engines: Scalar | Redoc | SwaggerUi | Elements.
    */
    'docs' => [
        'enabled' => env('TYPESCRIPT_API_DOCS', true),
        'engine' => PreviewEngine::Scalar,
        'route' => '/docs/api',
        'middleware' => ['web'],
        'environments' => ['local', 'staging'],
    ],
];
