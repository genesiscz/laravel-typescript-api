<?php

namespace Genesiscz\LaravelTypescriptApi\Docs;

enum PreviewEngine: string
{
    case Scalar = 'scalar';
    case Redoc = 'redoc';
    case SwaggerUi = 'swagger-ui';
    case Elements = 'elements';

    public function view(): string
    {
        return "typescript-api::docs.{$this->value}";
    }
}
