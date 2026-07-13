# laravel-typescript-api — Scaffold Plan

**Date:** 2026-07-13 · **Board:** https://vitrinka.in/w/martin/boards/laravel-typescript-api
**Research:** GenesisBrain `Dev/LaravelTypescriptApi/Research.md` + `Dev/Laravel/LaravelToTypescript.md`

## Decisions (user, 2026-07-13)

- **Both engines**: Scramble → openapi.json → Orval AND/OR hey-api, selectable/parallel via config (`engines` map, both can run in one generate).
- **scramble-extras as dependency**, expand it (fill gaps: multipart, polymorphic, transformer-attributes).
- **Support Spatie TypeScriptTransformer attributes** (`#[TypeScriptType]`, `#[LiteralTypeScriptType]`, `#[Optional]`) inside our Scramble pipeline. spatie/laravel-data required dep.
- **Steal 7nohe patterns**: queryKey factories, `prefetch…`/`ensure…` helpers — emitted as our own layer atop hey-api queryOptions (gives named hooks + options both).
- **Composable pipeline** modeled on TypeScriptTransformer concepts: Collectors (which classes/routes in), Scramble Extensions (PHP→schema), Engines (openapi→TS), Emitters (extra TS: hooks/enums/prefetch), Formatters (prettier/biome), Writers (where files land). All swappable via config.
- **Docs**: controller annotations (ReservineBack-style), `LaravelTypescriptApi::generateDocs(engine: PreviewEngine::Redoc)`, swappable preview engines (Redoc/Scalar/SwaggerUI/Elements), dynamic route.
- **Command**: shipped `laravel-typescript-api:generate` + all internals composable so apps write own commands (like ReservineBack `app:types`).
- **Config philosophy**: deep arrays, raw `passthrough` blocks merged verbatim into engine configs (near-zero BC maintenance), class-string extension points everywhere.
- **Reference/test bed**: ReservineBack worktree `feat/openapi-generation`, link lib via path repo, verify parity with `app:types` output. (Later phase.)
- Bun for all TS tooling (`bunx orval`, `bunx @hey-api/openapi-ts`).

## Package identity

- composer: `genesiscz/laravel-typescript-api` (rename cheap)
- namespace: `Genesiscz\LaravelTypescriptApi`
- PHP ^8.2, Laravel ^11|^12, spatie/laravel-data ^4, dedoc/scramble ^0.12.20, pjadanowski/scramble-extras ^0.2

## Architecture

```
GeneratePipeline
 1. Collect      Collectors: routes (scramble) + extra schema classes (Data namespaces)
 2. Describe     Scramble + scramble-extras + OUR extensions
                   - TransformerAttributesExtension (#[TypeScriptType], #[LiteralTypeScriptType], #[Optional])
                   - EnumVarnamesExtension (x-enum-varnames from PHP case names)
                   - EnvelopeExtension (api-response-builder unwrap description)
                   → openapi.json artifact
 3. Generate     Engines (parallel-capable): HeyApiEngine, OrvalEngine
                   each = writes engine config file + bunx run
 4. Emit         Emitters: HooksEmitter (named hooks atop hey-api queryOptions),
                   PrefetchEmitter, EnsureEmitter, EnumsEmitter (real TS enums),
                   IndexEmitter (barrel exports)
 5. Format       Formatter: Biome | Prettier | none | custom class
 6. Write        Writer: output tree, clean policy, manifest
```

Facade `LaravelTypescriptApi`: `generate()`, `generateDocs()`, `openapi()`, `pipeline()` — every step callable standalone.

## Tasks

1. ✅ git init, dirs
2. composer.json, LICENSE, .gitignore, pint/phpstan config
3. `config/typescript-api.php` — THE design centerpiece
4. ServiceProvider + Facade
5. Pipeline contracts (Collector, Engine, Emitter, Formatter, Writer interfaces) + GeneratePipeline
6. Scramble extensions (3× skeleton with TODO bodies where deep inference needed)
7. Engines: HeyApiEngine + OrvalEngine (config template + bunx runner)
8. Emitters skeletons
9. GenerateCommand + DocsCommand
10. Docs: PreviewEngine enum + blade stubs (redoc/scalar/swagger-ui/elements)
11. README.md — top shelf
12. Pest test skeleton + fixture Data classes
13. (later round) ReservineBack worktree feat/openapi-generation integration test

## VERIFY
- `composer validate` passes
- `vendor/bin/pest` green (skeleton tests)
- README renders complete story
