<?php

namespace Genesiscz\LaravelTypescriptApi\Facades;

use Genesiscz\LaravelTypescriptApi\LaravelTypescriptApiManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext generate(?array $config = null)
 * @method static \Genesiscz\LaravelTypescriptApi\Pipeline\GeneratePipeline pipeline()
 * @method static array openapi(?array $config = null)
 * @method static string generateDocs(?\Genesiscz\LaravelTypescriptApi\Docs\PreviewEngine $engine = null, ?string $artifactPath = null)
 *
 * @see LaravelTypescriptApiManager
 */
class LaravelTypescriptApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelTypescriptApiManager::class;
    }
}
