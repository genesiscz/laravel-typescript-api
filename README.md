# Laravel TypeScript API

**Your Laravel app, fully typed on the frontend — one command.**

`laravel-typescript-api` reads your controllers and [spatie/laravel-data](https://github.com/spatie/laravel-data) DTOs and generates a complete, typed TypeScript API layer: request/response models, an HTTP client, TanStack Query bindings for **React and Angular**, real TypeScript enums, optional zod schemas and MSW mocks — plus browsable OpenAPI docs from the same source of truth.

```bash
php artisan laravel-typescript-api:generate
```

```tsx
// React — everything below is generated and fully typed
const { data } = useQuery(listInvoicesOptions({ query: { page: 1 } }));
//    ^? ListInvoicesResponse

const create = useMutation(createInvoiceMutation());
create.mutate({ body: { customerId, items } });   // typed against CreateInvoiceRequest
```

```ts
// Angular — same spec, angular-query injectables (Orval engine)
invoices = injectListInvoices(() => ({ page: this.page() }));
```

## How it works

```
spatie/laravel-data DTOs + your controllers
        │
        ▼
  Scramble (+ scramble-extras + our extensions)   ← static analysis, no PHPDoc tax
        │
        ▼
  openapi.json                                    ← the contract; also powers /docs/api
        │
        ├──▶ @hey-api/openapi-ts   (types, SDK, TanStack queryOptions/hooks, zod)
        ├──▶ Orval                 (react-query / angular-query, MSW mocks)
        │
        ▼
  emitters (prefetch/ensure helpers, enums.ts, barrels) → formatter (biome/prettier)
```

- **[Scramble](https://scramble.dedoc.co)** infers your OpenAPI document from code — routes, controller signatures, laravel-data request/response types.
- **[scramble-extras](https://github.com/pjadanowski/scramble-extras)** deepens laravel-data support: `MapName`, `Optional`, `Lazy`, `#[Computed]`, `#[Hidden]`, ~80 validation attributes, paginated collections.
- **Our extensions** add what's still missing: Spatie typescript-transformer attribute support (`#[TypeScriptType]`, `#[LiteralTypeScriptType]`), PHP enum case names as `x-enum-varnames` (real named TS enums), response-envelope unwrapping.
- **Engines** turn the spec into TypeScript. Ship [hey-api](https://heyapi.dev), [Orval](https://orval.dev), or both — each into its own directory, from the same artifact.
- **Emitters** add the layer nobody else generates: `prefetchListInvoices()` / `ensureListInvoicesData()` helpers for SSR and router loaders, an authoritative `enums.ts`, barrel exports.

Everything runs through **bun** (`bunx`) — no node_modules ceremony in your Laravel repo.

## Installation

```bash
composer require genesiscz/laravel-typescript-api
php artisan vendor:publish --tag=typescript-api-config
```

Requirements: PHP 8.2+, Laravel 11+, spatie/laravel-data ^4, bun on PATH.

## Quick start

1. Type your controllers with Data classes:

```php
class InvoicesController extends Controller
{
    public function list(ListInvoicesRequest $request): ListInvoicesResponse { … }
    public function create(CreateInvoiceRequest $request): InvoiceData { … }
}
```

2. Generate:

```bash
php artisan laravel-typescript-api:generate
```

3. Import from the generated tree (`resources/ts/api` by default):

```ts
import { listInvoicesOptions, createInvoiceMutation } from '@/api';
import { InvoiceState } from '@/api/enums';
```

## Configuration philosophy

The config is a **composable pipeline** — every stage is a class-string you can swap, extend or reorder, in the spirit of spatie/typescript-transformer:

| Stage | Contract | Ships with |
|---|---|---|
| Collectors | `Contracts\Collector` | `DataNamespaceCollector` — include every Data class under `App\Data`, `App\Http\Requests`, `App\Http\Responses` by convention, no attributes needed |
| Scramble extensions | `TypeToSchemaExtension` | transformer-attributes, enum varnames, response envelope |
| Engines | `Contracts\Engine` | `HeyApiEngine`, `OrvalEngine` |
| Emitters | `Contracts\Emitter` | hooks, prefetch/ensure, enums, barrels |
| Formatter | `Contracts\Formatter` | Biome, Prettier |

And every engine block carries a **`passthrough`** array merged verbatim into the generated engine config (`openapi-ts.config.ts` / `orval.config.ts`). New upstream option shipped yesterday? Use it today — no package release needed, near-zero BC surface:

```php
HeyApiEngine::class => [
    'enabled' => true,
    'plugins' => [
        '@hey-api/typescript',
        ['name' => '@hey-api/sdk', 'validator' => 'zod'],
        ['name' => '@tanstack/react-query', 'useQuery' => true, 'infiniteQueryOptions' => true],
        'zod',
        ['name' => '@hey-api/transformers', 'dates' => true],
    ],
    'passthrough' => [
        'output' => ['format' => 'biome', 'lint' => 'biome'],
    ],
],
```

Run both engines at once — they consume the same `openapi.json`:

```php
'engines' => [
    HeyApiEngine::class => ['enabled' => true,  'dir' => ''],
    OrvalEngine::class  => ['enabled' => true,  'dir' => 'angular', 'client' => 'angular-query'],
],
```

## Compose your own command

The shipped command is a thin wrapper. Every stage is public — build your own `app:types` like you always have:

```php
use Genesiscz\LaravelTypescriptApi\Facades\LaravelTypescriptApi;

$pipeline = LaravelTypescriptApi::pipeline();

$context = $pipeline->newContext($myConfig);
$pipeline->openapi($context);       // just the spec
$pipeline->engines($context);       // just the TS generation
$pipeline->emitters($context);      // just the extras
$pipeline->format($context);        // just formatting

// or the one-liner:
LaravelTypescriptApi::generate();
```

## API docs from the same spec

The `openapi.json` that types your frontend also renders your docs — one source of truth, zero drift:

```php
use Genesiscz\LaravelTypescriptApi\Docs\PreviewEngine;

LaravelTypescriptApi::generateDocs(engine: PreviewEngine::Redoc);   // static docs.html
```

Or browse live at `/docs/api` (configurable route, environment-gated). Preview engines: **Scalar** (default), **Redoc**, **Swagger UI**, **Stoplight Elements** — swap via config.

## Response envelopes

Wrapping responses (e.g. `marcin-orlowski/laravel-api-response-builder`'s `{ success, code, data }`)? Configure the unwrap once:

```php
'openapi' => [
    'envelope' => ['unwrap' => 'data', 'document_full' => true],
],
```

Client types describe the **inner** payload (what your interceptor actually returns); docs still show the full wrapper.

## Enums that match your PHP

OpenAPI alone gives you `'active' | 'inactive'`. Our `EnumVarnamesExtension` stamps PHP case names into the spec and `EnumsEmitter` renders an authoritative `enums.ts`:

```ts
export enum InvoiceState {
    Draft = 'draft',
    Sent = 'sent',
    Paid = 'paid',
}
```

Native PHP enums and bensampo/laravel-enum both supported. Styles: `enum`, `const` (as-const object) or `union`.

## Migrating from spatie/laravel-typescript-transformer

Already generating types with typescript-transformer (attributes like `#[TypeScriptType]`, `#[LiteralTypeScriptType]`, `#[Optional]` everywhere)? Those attributes are honored inside the OpenAPI pipeline — your annotations keep working, and now they type a whole client, not just interfaces.

The `AmbientNamespaceEmitter` makes the migration a drop-in: it writes a typescript-transformer-style `app.d.ts` (`declare namespace App.Data { … }` — frontends keep using `App.Data.X` globally, zero imports) plus the matching runtime `enums.ts`, from the same OpenAPI document the engines consume. The pieces that make it 1:1 in practice:

```php
AmbientNamespaceEmitter::class => [
    'enabled' => true,
    'path' => resource_path('types'),      // overwrite the old output in place
    'enum_import' => './enums',            // props type against the RUNTIME enums
    'exclude_namespaces' => ['App.Models'],// keep models.d.ts as its own generator
],
```

- `#[TypeScript]`-attributed plain classes export too (`TypeScriptAttributeCollector`) — notification payloads, service DTOs.
- `bensampo/laravel-enum` class enums come out as real TS enums.
- `class_type_map` replaces custom transformers (`Money::class => 'number'`, `ArrayObject::class => 'Record<string, unknown>'`).
- `input_namespaces` builds request DTOs as input schemas (`#[Hidden]` props the frontend sends still appear).
- References survive namespace shadowing (`App\Http\Requests\App` …) via shortest-unambiguous-suffix emission.

Verified against a 285-route production app: dropped into an Angular nx monorepo that consumed the old typescript-transformer output, the generated pair took the app's typecheck from 414 errors (naive generation) to 8 — all 8 being backend/frontend branch skew or frontend code that only compiled because the old types said `any`.

## Roadmap

- [x] Scramble extensions: transformer-attributes, enum varnames, envelope, bensampo enums, `#[TypeScript]` classes
- [x] Collector-appended schemas via Scramble's TypeTransformer (document transformer)
- [x] Emitters: enums / barrels / ambient app.d.ts (typescript-transformer drop-in)
- [ ] Emitters: prefetch / ensure helpers as a hey-api plugin
- [ ] `--watch` mode
- [ ] Multipart uploads + polymorphic Data (upstreaming to scramble-extras where it belongs)
- [x] Reference integration: ReservineBack end-to-end (react + angular tanstack + orval, `tsc --strict` clean)

## Credits

Standing on excellent shoulders: [dedoc/scramble](https://github.com/dedoc/scramble), [pjadanowski/scramble-extras](https://github.com/pjadanowski/scramble-extras), [hey-api](https://github.com/hey-api/hey-api), [Orval](https://github.com/orval-labs/orval), [spatie/laravel-data](https://github.com/spatie/laravel-data), with ideas from [flow96/laravel-bridge](https://github.com/flow96/laravel-bridge) and [7nohe/openapi-react-query-codegen](https://github.com/7nohe/openapi-react-query-codegen).

## License

MIT
