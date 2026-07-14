<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble\Extensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types as OpenApi;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * bensampo/laravel-enum support: class-based enums (class constants, no
 * native PHP enum) become the same enum schemas native enums get — values
 * plus x-enum-varnames with the constant names — so engines and the
 * ambient/enums emitters emit real named TS enums for them too.
 *
 * No hard dependency: only activates when a type actually extends
 * BenSampo\Enum\Enum.
 */
class BenSampoEnumExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && class_exists($type->name)
            && is_a($type->name, 'BenSampo\\Enum\\Enum', true);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): OpenApi\Type
    {
        /** @var array<string, int|string> $constants */
        $constants = array_filter(
            (new \ReflectionClass($type->name))->getConstants(),
            fn ($value) => is_int($value) || is_string($value),
        );

        if ($constants === []) {
            return new UnknownType;
        }

        $values = array_values($constants);

        $schemaType = is_string($values[0]) ? new StringType : new IntegerType;
        $schemaType->enum($values);

        if (config('scramble.enum_cases_names_strategy') === 'varnames') {
            $schemaType->setExtensionProperty('enum-varnames', array_keys($constants));
        }

        return $schemaType;
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
