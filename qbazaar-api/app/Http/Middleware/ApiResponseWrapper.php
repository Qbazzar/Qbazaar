<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every API response follows the contract envelope:
 *
 *   Success:  { success: true,  data: ..., meta?: { pagination... } }
 *   Error:    { success: false, error: { code, message_key, message, details?, request_id } }
 *
 * Controllers may return any of:
 *  - JsonResource / ResourceCollection (preferred — auto-paginated)
 *  - Eloquent Model / Collection (auto-wrapped)
 *  - array / scalar
 *  - already-shaped envelope (left untouched)
 *
 * Errors are NOT shaped here — that's done in the global exception handler
 * (see bootstrap/app.php) so this middleware only deals with the success path.
 *
 * Adds X-Request-Id to every response for log correlation.
 */
class ApiResponseWrapper
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: Str::uuid()->toString();
        $request->headers->set('X-Request-Id', $requestId);

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response = $this->wrap($response);
        }

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function wrap(JsonResponse $response): JsonResponse
    {
        // 204 / empty body — nothing to wrap.
        if ($response->getStatusCode() === Response::HTTP_NO_CONTENT) {
            return $response;
        }

        $payload = $response->getData(true);

        // Already wrapped (success or error envelope) — leave alone.
        if (is_array($payload) && array_key_exists('success', $payload)) {
            return $response;
        }

        // Resource collections expose `data` + `meta`/`links` natively.
        if (is_array($payload) && array_key_exists('data', $payload) && (
            array_key_exists('meta', $payload) || array_key_exists('links', $payload)
        )) {
            $meta = $payload['meta'] ?? [];
            // Flatten Laravel paginator meta into our shape
            $envelope = [
                'success' => true,
                'data' => $payload['data'],
                'meta' => $this->normalisePaginationMeta($meta),
            ];

            return $response->setData($envelope);
        }

        // Plain data — wrap.
        $envelope = [
            'success' => true,
            'data' => $payload,
        ];

        return $response->setData($envelope);
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function normalisePaginationMeta(array $meta): array
    {
        // Laravel paginator shape \xe2\x86\x92 our shape
        return [
            'current_page' => $meta['current_page'] ?? null,
            'per_page' => $meta['per_page'] ?? null,
            'total' => $meta['total'] ?? null,
            'last_page' => $meta['last_page'] ?? null,
            'has_more' => isset($meta['current_page'], $meta['last_page'])
                ? ($meta['current_page'] < $meta['last_page'])
                : null,
            'next_cursor' => $meta['next_cursor'] ?? null,
            'prev_cursor' => $meta['prev_cursor'] ?? null,
        ];
    }
}
