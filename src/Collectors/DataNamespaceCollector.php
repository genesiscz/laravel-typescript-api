<?php

namespace Genesiscz\LaravelTypescriptApi\Collectors;

use Genesiscz\LaravelTypescriptApi\Contracts\Collector;
use Illuminate\Support\Str;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Symfony\Component\Finder\Finder;

/**
 * Convention over annotation: every concrete Data subclass under the
 * configured namespaces is included — no #[TypeScript] attribute needed.
 * Direct port of ReservineBack's OwnCollector idea.
 */
class DataNamespaceCollector implements Collector
{
    public function collect(array $options): iterable
    {
        $namespaces = $options['namespaces'] ?? ['App\\Data'];
        $except = $options['except'] ?? [];

        foreach ($namespaces as $namespace) {
            $path = $this->namespaceToPath($namespace);

            if (! is_dir($path)) {
                continue;
            }

            foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
                $class = $namespace.'\\'.Str::of($file->getRelativePathname())
                    ->beforeLast('.php')
                    ->replace('/', '\\');

                if (in_array($class, $except, true) || ! class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Data::class)) {
                    continue;
                }

                yield $class;
            }
        }
    }

    protected function namespaceToPath(string $namespace): string
    {
        // PSR-4 assumption for the host app: App\ => app/
        return base_path(Str::of($namespace)
            ->replaceFirst('App\\', 'app/')
            ->replace('\\', '/')
            ->toString());
    }
}
