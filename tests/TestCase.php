<?php

namespace Genesiscz\LaravelTypescriptApi\Tests;

use Dedoc\Scramble\ScrambleServiceProvider;
use Genesiscz\LaravelTypescriptApi\LaravelTypescriptApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ScrambleServiceProvider::class,
            LaravelTypescriptApiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('typescript-api.output.path', sys_get_temp_dir().'/typescript-api-tests');
        $app['config']->set('typescript-api.docs.enabled', false);
    }
}
