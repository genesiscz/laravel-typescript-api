<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Spatie\LaravelData\Contracts\BaseData;

/**
 * Types responses that scramble cannot infer — controllers returning an
 * opaque envelope (marcin-orlowski/laravel-api-response-builder et al.)
 * via `$this->response($data)` produce `{type: object}` 200s.
 *
 * The hook is the docblock convention already used in ReservineBack:
 *
 *     /**
 *      * Get branch details
 *      *
 *      * - Response: BranchResponse
 *      *\/
 *
 * The named class (resolved against openapi.envelope.response_namespaces)
 * is transformed through the regular schema pipeline and becomes the 200
 * body. With openapi.envelope.unwrap set (e.g. 'data'), the documented
 * schema is the INNER payload — matching what callers see after the
 * client interceptor unwraps the envelope.
 */
class ResponseEnvelopeExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $responseClass = $this->responseClassFromDocblock($routeInfo);

        if ($responseClass === null) {
            return;
        }

        $schema = Schema::fromType(
            $this->openApiTransformer->transform(new ObjectType($responseClass)),
        );

        // Replace the inferred (opaque) 200 with the documented type.
        $operation->responses = array_values(array_filter(
            $operation->responses ?? [],
            fn ($response) => ! ($response instanceof Response && (int) $response->code === 200),
        ));

        $operation->addResponse(
            Response::make(200)->setContent('application/json', $schema),
        );
    }

    /** @return class-string|null */
    protected function responseClassFromDocblock(RouteInfo $routeInfo): ?string
    {
        $docComment = $routeInfo->reflectionMethod()?->getDocComment();

        if ($docComment === false || $docComment === null) {
            return null;
        }

        if (! preg_match('/-\s*Response:\s*([\w\\\\]+)/', $docComment, $matches)) {
            return null;
        }

        return $this->resolveClass($matches[1]);
    }

    /** @var array<string, class-string>|null basename => FQCN, built once per process */
    protected static ?array $classIndex = null;

    /** @return class-string|null */
    protected function resolveClass(string $name): ?string
    {
        if (class_exists($name) && is_a($name, BaseData::class, true)) {
            return $name;
        }

        return $this->classIndex()[$name] ?? null;
    }

    /**
     * Docblocks name bare classes ("- Response: BranchResponse") while the
     * classes live in arbitrary subnamespaces (App\Http\Responses\Branch\…).
     * Index every Data subclass under the configured roots by basename.
     *
     * @return array<string, class-string>
     */
    protected function classIndex(): array
    {
        if (static::$classIndex !== null) {
            return static::$classIndex;
        }

        $namespaces = config('typescript-api.openapi.envelope.response_namespaces', [
            'App\\Http\\Responses\\',
            'App\\Data\\',
        ]);

        $index = [];

        foreach ($namespaces as $namespace) {
            $namespace = trim($namespace, '\\');
            $path = base_path(str_replace(['App\\', '\\'], ['app/', '/'], $namespace));

            if (! is_dir($path)) {
                continue;
            }

            foreach (\Symfony\Component\Finder\Finder::create()->files()->in($path)->name('*.php') as $file) {
                $class = $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

                if (! class_exists($class) || ! is_a($class, BaseData::class, true)) {
                    continue;
                }

                // First hit wins — namespace order in config is the priority.
                $index[class_basename($class)] ??= $class;
            }
        }

        return static::$classIndex = $index;
    }
}
