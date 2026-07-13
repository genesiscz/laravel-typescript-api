<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble\Extensions;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Genesiscz\LaravelTypescriptApi\Scramble\TransformerAwareReflector;
use PawelJadanowski\ScrambleExtras\LaravelDataReflector;
use PawelJadanowski\ScrambleExtras\LaravelDataTypeToSchemaExtension;

/**
 * scramble-extras' Data extension, hardened:
 *
 * 1. FORCES the reflector to run. Scramble core sees a Data class as an
 *    opaque Arrayable and emits an EMPTY object schema first; extras then
 *    defers to that "previous result" and the empty schema wins (observed on
 *    ReservineBack: 140/184 Data schemas empty). Registered after extras,
 *    this extension ignores the previous result and rebuilds.
 *
 * 2. Uses TransformerAwareReflector, honoring spatie/typescript-transformer
 *    attributes (#[Optional] → not required).
 */
class TransformerAttributesExtension extends LaravelDataTypeToSchemaExtension
{
    public function toSchema(Type $type, ?OpenApiType $previousExtensionResult = null): OpenApiType
    {
        /** @var ObjectType $type */
        return $this->reflector()->buildSchema($type->name, input: false);
    }

    protected function reflector(): LaravelDataReflector
    {
        return new TransformerAwareReflector($this->openApiTransformer, $this->components);
    }
}
