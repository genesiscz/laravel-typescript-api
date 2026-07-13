<?php

namespace Genesiscz\LaravelTypescriptApi\Contracts;

interface Collector
{
    /**
     * Class-strings to force into the OpenAPI document as component schemas
     * even when no endpoint references them (shared DTOs, broadcast
     * payloads). Mirrors typescript-transformer's collector concept.
     *
     * @return iterable<class-string>
     */
    public function collect(array $options): iterable;
}
