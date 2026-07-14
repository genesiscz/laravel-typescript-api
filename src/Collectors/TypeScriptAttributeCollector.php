<?php

namespace Genesiscz\LaravelTypescriptApi\Collectors;

use Genesiscz\LaravelTypescriptApi\Contracts\Collector;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * spatie/typescript-transformer's own convention: any class marked with
 * the #[TypeScript] attribute is exported. Lets classes OUTSIDE the Data
 * namespaces (notification payloads, service DTOs) opt in exactly like
 * they did under typescript-transformer.
 *
 * options.namespaces: roots to scan (default ['App\\']).
 */
class TypeScriptAttributeCollector implements Collector
{
    public function collect(array $options): iterable
    {
        $namespaces = $options['namespaces'] ?? ['App\\'];
        $attribute = 'Spatie\\TypeScriptTransformer\\Attributes\\TypeScript';

        foreach ($namespaces as $namespace) {
            $namespace = trim($namespace, '\\');
            $path = base_path(Str::of($namespace)
                ->replaceFirst('App', 'app')
                ->replace('\\', '/')
                ->toString());

            if (! is_dir($path)) {
                continue;
            }

            foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
                // Cheap pre-filter before autoloading the class.
                if (! str_contains((string) file_get_contents($file->getPathname()), '#[TypeScript')) {
                    continue;
                }

                $class = $namespace.'\\'.Str::of($file->getRelativePathname())
                    ->beforeLast('.php')
                    ->replace('/', '\\');

                if (! class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if (! $reflection->isAbstract() && $reflection->getAttributes($attribute) !== []) {
                    yield $class;
                }
            }
        }
    }
}
