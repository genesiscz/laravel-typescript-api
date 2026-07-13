<?php

namespace Genesiscz\LaravelTypescriptApi\OpenApi;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Produces the OpenAPI document (array form) from the configured source:
 * live Scramble inference (default), a pre-built spec file, or a callable.
 * Extra schema classes from collectors are appended as component schemas.
 */
class OpenApiBuilder
{
    public function __construct(
        protected SchemaAppender $appender,
    ) {}

    /**
     * @param  list<class-string>  $extraSchemas
     * @return array<string, mixed>
     */
    public function build(array $config, array $extraSchemas = []): array
    {
        $document = match (true) {
            is_callable($config['source'] ?? null) => ($config['source'])(),
            ($config['source'] ?? 'scramble') === 'file' => $this->fromFile($config['file'] ?? null),
            default => $this->fromScramble($config['scramble'] ?? []),
        };

        if ($extraSchemas !== []) {
            $document = $this->appender->append($document, $extraSchemas);
        }

        return $document;
    }

    protected function fromFile(?string $path): array
    {
        if ($path === null || ! File::exists($path)) {
            throw new InvalidArgumentException("openapi.source is 'file' but openapi.file [{$path}] does not exist.");
        }

        return json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @var list<string> analysis errors collected during the last build */
    public array $errors = [];

    protected function fromScramble(array $scrambleConfig): array
    {
        $this->registerExtensions($scrambleConfig['extensions'] ?? []);

        // Scramble config overrides for the duration of the build
        // (e.g. enum_cases_names_strategy => 'varnames').
        foreach ($scrambleConfig['config'] ?? [] as $key => $value) {
            config(["scramble.{$key}" => $value]);
        }

        $api = $scrambleConfig['api'] ?? 'default';

        // Scramble 0.12: generate the document for the given API registration.
        // Collect per-route analysis exceptions instead of aborting — one
        // unanalyzable route must not kill the whole artifact — but SURFACE
        // them: every swallowed exception leaves a broken string-placeholder
        // schema ("Reference is being analyzed") behind.
        $generator = app(Generator::class)->setThrowExceptions(false);
        $document = $generator(Scramble::getGeneratorConfig($api));

        $this->errors = array_map(
            fn (\Throwable $e) => $e->getMessage(),
            $generator->exceptions,
        );

        return is_array($document) ? $document : $document->toArray();
    }

    protected function registerExtensions(array $extensions): void
    {
        foreach ($extensions as $key => $value) {
            $class = is_int($key) ? $value : $key;

            Scramble::registerExtension($class);
        }
    }
}
