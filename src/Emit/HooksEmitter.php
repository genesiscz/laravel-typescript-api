<?php

namespace Genesiscz\LaravelTypescriptApi\Emit;

use Genesiscz\LaravelTypescriptApi\Contracts\Emitter;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;

/**
 * Named hooks on top of hey-api queryOptions:
 *
 *   export const useListInvoices = (options?, queryOpts?) =>
 *     useQuery({ ...listInvoicesOptions(options), ...queryOpts });
 *
 * NOTE: hey-api v0.99's @tanstack/react-query plugin can emit hooks itself
 * (useQuery: true / useMutation: true) — prefer those flags when they fit.
 * This emitter exists for output-shape control (naming, envelope-typed
 * results) and for engines/situations without native hook emission.
 *
 * TODO(scaffold): implement — walk context->openapi paths/operations,
 * derive hook names from operationId, write react/<tag>.hooks.ts.
 */
class HooksEmitter implements Emitter
{
    public function emit(GenerateContext $context, array $options): void
    {
        // TODO(scaffold)
    }
}
