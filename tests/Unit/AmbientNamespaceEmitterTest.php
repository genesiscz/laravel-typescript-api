<?php

use Genesiscz\LaravelTypescriptApi\Emit\AmbientNamespaceEmitter;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;
use Genesiscz\LaravelTypescriptApi\Support\BunRunner;

function ambientContext(array $schemas, string $outputPath): GenerateContext
{
    $context = new GenerateContext(
        config: [],
        outputPath: $outputPath,
        runner: BunRunner::fromConfig([]),
    );

    $context->openapi = ['components' => ['schemas' => $schemas]];

    return $context;
}

it('emits ambient namespaces with shadow-safe references and const enums', function () {
    $out = sys_get_temp_dir().'/lta-ambient-'.uniqid();

    $schemas = [
        'App.Enums.BranchType' => [
            'type' => 'string',
            'enum' => ['sport', 'wellness'],
            'x-enum-varnames' => ['Sport', 'Wellness'],
        ],
        'App.Data.BranchData' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'type' => ['$ref' => '#/components/schemas/App.Enums.BranchType'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'note' => ['type' => ['string', 'null']],
            ],
            'required' => ['id', 'type', 'tags'],
        ],
        // PHP namespace App\Http\Requests\App shadows the root `App` for
        // every sibling namespace — references must survive it.
        'App.Http.Requests.App.InquiryRequest' => [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ],
        'App.Http.Requests.Admin.BarRequest' => [
            'type' => 'object',
            'properties' => [
                'branch' => ['$ref' => '#/components/schemas/App.Data.BranchData'],
                'type' => ['$ref' => '#/components/schemas/App.Enums.BranchType'],
            ],
            'required' => ['branch', 'type'],
        ],
    ];

    (new AmbientNamespaceEmitter)->emit(ambientContext($schemas, $out), []);

    $dts = file_get_contents($out.'/app.d.ts');

    expect($dts)
        ->toContain('declare namespace App.Data {')
        ->toContain('declare namespace App.Enums {')
        ->toContain("export const enum BranchType {\n\t\tSport = 'sport',\n\t\tWellness = 'wellness',\n\t}")
        ->toContain('export type BranchData = {')
        ->toContain('tags: Array<string>;')
        ->toContain('note?: string | null;')
        // Shadow-safe suffix references, never a bare `App.` prefix that
        // App.Http.Requests.App would capture:
        ->toContain('branch: Data.BranchData;')
        ->toContain('type: Enums.BranchType;');

    // Optional (not required) property renders with `?`
    expect($dts)->not->toContain('note: string');
});
