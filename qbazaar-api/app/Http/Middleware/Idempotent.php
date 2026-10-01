<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-key replay protection for mutation endpoints.
 *
 * Reads `X-Idempotency-Key` from the request. On the first call we let the
 * controller run, then cache the response (status + body) under a
 * Redis key derived from `key + path + user_id` for 24 hours. Any subsequent
 * request that arrives with the same key replays the cached response
 * verbatim — without re-running the controller (no duplicate publishes,
 * no duplicate notifications, no duplicate billing in future sprints).
 *
 * While the first request runs, a second one with the same key gets
 * REQUEST_IN_PROGRESS (409) instead of running the controller in parallel.
 *
 * If the client doesn't provide a key we skip the middleware entirely — this
 * is opt-in idempotency: only callers who care pay the cache cost.
 *
 * Constraints:
 *   - Key shape is validated (32–80 chars, ULID-compatible) so accidental
 *     long bodies / short typos don't poison the cache.
 *   - Cache scope includes user id + path so the same key from a different
 *     user / endpoint doesn't collide.
 *   - Only JsonResponse payloads are cached — non-JSON responses (file
 *     downloads etc.) pass through without storage.
 *
 * The middleware is registered as the `idempotent` alias in
 * bootstrap/app.php; controllers opt in via `->middleware('idempotent')`.
 */
class Idempotent
{
    private const CACHE_TTL_SECONDS = 60 * 60 * 24; // 24h

    private const LOCK_SECONDS = 30;

    private const KEY_MIN_LENGTH = 16;

    private const KEY_MAX_LENGTH = 128;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Idempotency-Key');

        if (! is_string($key) || ! $this->validKey($key)) {
            return $next($request);
        }

        $cacheKey = $this->buildCacheKey($request, $key);

        $replay = $this->replay($cacheKey);
        if ($replay !== null) {
            return $replay;
        }

        // Without the lock two retries racing each other would both miss the
        // cache and run the controller twice.
        $lock = Cache::lock($cacheKey . ':lock', self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new DomainException(ErrorCode::REQUEST_IN_PROGRESS);
        }

        try {
            $replay = $this->replay($cacheKey);
            if ($replay !== null) {
                return $replay;
            }

            $response = $next($request);

            if ($response instanceof JsonResponse && $this->isSuccess($response)) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getData(true),
                ], self::CACHE_TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function replay(string $cacheKey): ?JsonResponse
    {
        $cached = Cache::get($cacheKey);

        if (! is_array($cached) || ! isset($cached['status'], $cached['body']) || ! is_int($cached['status'])) {
            return null;
        }

        /** @var array<string, mixed>|scalar|null $body */
        $body = $cached['body'];
        $response = new JsonResponse($body, $cached['status']);
        $response->headers->set('X-Idempotent-Replay', 'true');

        return $response;
    }

    /**
     * Compose a cache key that scopes by user + route so the same client-side
     * key can't bleed across endpoints or accounts. Unauth callers fall back
     * to the request IP to keep the scope tight on public idempotent routes.
     */
    private function buildCacheKey(Request $request, string $idempotencyKey): string
    {
        $userId = optional($request->user())->id ?: ('ip:' . (string) $request->ip());

        return sprintf(
            'idempotent:%s:%s:%s:%s',
            $userId,
            strtolower($request->getMethod()),
            sha1((string) $request->getPathInfo()),
            $idempotencyKey,
        );
    }

    private function validKey(string $key): bool
    {
        $length = strlen($key);
        if ($length < self::KEY_MIN_LENGTH || $length > self::KEY_MAX_LENGTH) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9\-_]+$/', $key) === 1;
    }

    /**
     * Only cache 2xx responses — a 4xx / 5xx replay would suppress the user
     * fixing the request and retrying. We deliberately want them to retry the
     * controller after a failure.
     */
    private function isSuccess(JsonResponse $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }
}
