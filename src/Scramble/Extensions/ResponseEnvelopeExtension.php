<?php

namespace Genesiscz\LaravelTypescriptApi\Scramble\Extensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Response-envelope awareness (marcin-orlowski/laravel-api-response-builder
 * and friends). With openapi.envelope.unwrap configured (e.g. 'data'), the
 * documented response schema is the INNER payload — matching what callers
 * see after the client interceptor/mutator unwraps. With
 * envelope.document_full, the full `{success, code, data}` wrapper is kept
 * for the docs preview while clients still type the inner shape.
 *
 * TODO(scaffold): implement — detect the envelope on controller responses
 * (BaseApiController::response() style) and rewrap/unwrap per config.
 */
class ResponseEnvelopeExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        // TODO(scaffold)
        return false;
    }

    public function toSchema(Type $type): ?OpenApiType
    {
        // TODO(scaffold)
        return null;
    }
}
