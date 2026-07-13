<?php

use Genesiscz\LaravelTypescriptApi\Collectors\DataNamespaceCollector;
use Genesiscz\LaravelTypescriptApi\Docs\PreviewEngine;
use Genesiscz\LaravelTypescriptApi\Emit\EnumsEmitter;
use Genesiscz\LaravelTypescriptApi\Emit\HooksEmitter;
use Genesiscz\LaravelTypescriptApi\Emit\IndexEmitter;
use Genesiscz\LaravelTypescriptApi\Emit\PrefetchEmitter;
use Genesiscz\LaravelTypescriptApi\Engines\HeyApiEngine;
use Genesiscz\LaravelTypescriptApi\Engines\OrvalEngine;
use Genesiscz\LaravelTypescriptApi\Format\BiomeFormatter;
use Genesiscz\LaravelTypescriptApi\Scramble\Extensions\EnumVarnamesExtension;
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
                EnumVarnamesExtension::class,
                ResponseEnvelopeExtension::class,
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
        ],

        // Unwrap a response envelope so client types match what callers see
        // AFTER your mutator/interceptor unwraps, e.g. api-response-builder's
        // { success, code, data } => 'data'. null = no envelope.
        'envelope' => [
            'unwrap' => null,           // e.g. 'data'
            'document_full' => true,    // keep full envelope in docs
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
                '@hey-api/typescript',
                '@hey-api/sdk',
                '@tanstack/react-query',
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
        HooksEmitter::class => [
            'enabled' => true,          // useListInvoices() atop queryOptions
        ],
        PrefetchEmitter::class => [
            'enabled' => true,          // prefetchListInvoices(queryClient, …)
            'ensure' => true,           // ensureListInvoicesData(queryClient, …)
        ],
        EnumsEmitter::class => [
            'enabled' => true,
            'style' => 'enum',          // 'enum' | 'const' | 'union'
            'file' => 'enums.ts',
        ],
        IndexEmitter::class => [
            'enabled' => true,          // barrel exports
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
        'bun' => env('TYPESCRIPT_API_BUN', 'bunx'),
        'cwd' => null,                  // where bunx runs; null = base_path()
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
