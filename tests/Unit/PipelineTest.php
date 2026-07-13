<?php

use Genesiscz\LaravelTypescriptApi\Facades\LaravelTypescriptApi;
use Genesiscz\LaravelTypescriptApi\Pipeline\GeneratePipeline;

it('resolves the manager through the facade', function () {
    expect(LaravelTypescriptApi::pipeline())->toBeInstanceOf(GeneratePipeline::class);
});

it('registers the generate command', function () {
    $this->artisan('laravel-typescript-api:generate', ['--help' => true])
        ->assertSuccessful();
})->skip('enable once openapi stage works against a fixture app');

it('builds a context honoring configured output path', function () {
    $context = LaravelTypescriptApi::pipeline()->newContext();

    expect($context->outputPath)->toBe(sys_get_temp_dir().'/typescript-api-tests');
});
