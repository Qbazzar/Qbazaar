<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides Swagger UI and the raw OpenAPI spec unless they are switched on, so
 * production does not publish a map of every endpoint by default.
 */
class EnsureApiDocsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('qbazaar.api_docs_enabled'), 404);

        return $next($request);
    }
}
