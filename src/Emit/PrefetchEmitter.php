<?php

namespace Genesiscz\LaravelTypescriptApi\Emit;

use Genesiscz\LaravelTypescriptApi\Contracts\Emitter;
use Genesiscz\LaravelTypescriptApi\Pipeline\GenerateContext;

/**
 * SSR / router-loader helpers, pattern stolen from
 * 7nohe/openapi-react-query-codegen:
 *
 *   export const prefetchListInvoices = (queryClient, options?) =>
 *     queryClient.prefetchQuery(listInvoicesOptions(options));
 *
 *   export const ensureListInvoicesData = (queryClient, options?) =>
 *     queryClient.ensureQueryData(listInvoicesOptions(options));
 *
 * TODO(scaffold): implement — one prefetch + (options.ensure) one ensure
 * helper per GET operation, from context->openapi.
 */
class PrefetchEmitter implements Emitter
{
    public function emit(GenerateContext $context, array $options): void
    {
        // TODO(scaffold)
    }
}
