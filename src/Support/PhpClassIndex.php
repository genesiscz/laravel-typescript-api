<?php

namespace Genesiscz\LaravelTypescriptApi\Support;

use Symfony\Component\Finder\Finder;

/**
 * basename => FQCN index over configured namespace roots. OpenAPI schema
 * names are class basenames; anything that needs to reason about the
 * original PHP namespace (ambient `declare namespace App.Data` emission,
 * docblock response resolution) resolves through here.
 *
 * Candidates come from file paths (PSR-4), so nothing autoloads unless its
 * basename is actually wanted. First hit wins — namespace order is the
 * priority order. Enums and classes both index.
 */
class PhpClassIndex
{
    /**
     * basename => first matching FQCN.
     *
     * @param  list<string>  $namespaces
     * @param  list<string>|null  $onlyBasenames  restrict autoload+verify to these names
     * @return array<string, class-string>
     */
    public static function build(array $namespaces, ?array $onlyBasenames = null): array
    {
        return array_map(
            fn (array $candidates) => $candidates[0],
            static::buildAll($namespaces, $onlyBasenames),
        );
    }

    /**
     * basename => EVERY matching FQCN (namespace order = priority order).
     * Needed when a basename is ambiguous and the caller has extra context
     * to pick the right candidate.
     *
     * @param  list<string>  $namespaces
     * @param  list<string>|null  $onlyBasenames  restrict autoload+verify to these names
     * @return array<string, non-empty-list<class-string>>
     */
    public static function buildAll(array $namespaces, ?array $onlyBasenames = null): array
    {
        $wanted = $onlyBasenames === null ? null : array_flip($onlyBasenames);
        $index = [];

        foreach ($namespaces as $namespace) {
            $namespace = trim($namespace, '\\');
            $path = base_path(str_replace(['App\\', 'App', '\\'], ['app/', 'app', '/'], $namespace));

            if (! is_dir($path)) {
                continue;
            }

            foreach (Finder::create()->files()->in($path)->name('*.php')->sortByName() as $file) {
                $basename = $file->getFilenameWithoutExtension();

                if ($wanted !== null && ! isset($wanted[$basename])) {
                    continue;
                }

                $class = $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

                if (in_array($class, $index[$basename] ?? [], true)) {
                    continue;
                }

                if (class_exists($class) || enum_exists($class)) {
                    $index[$basename][] = $class;
                }
            }
        }

        return $index;
    }
}
