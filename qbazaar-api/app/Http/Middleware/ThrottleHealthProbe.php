<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies the `api` rate limiter to /health/ready but fails open when the
 * limiter's store is unreachable. The stock throttle middleware would throw
 * before the controller ran, turning "cache down" into a 500 instead of the
 * documented 503 report.
 */
class ThrottleHealthProbe
{
    private static bool $loggedFailure = false;

    public function __construct(private readonly LoggerInterface $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $retryAfter = $this->retryAfter($request);
        } catch (Throwable $e) {
            $this->logOnce($e);

            return $next($request);
        }

        if ($retryAfter !== null) {
            return response()->json(['message' => 'Too Many Requests'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => $retryAfter]);
        }

        return $next($request);
    }

    /** Seconds to wait when over the limit, otherwise null (the attempt is counted). */
    private function retryAfter(Request $request): ?int
    {
        $resolver = RateLimiter::limiter('api');
        $limits = $resolver === null ? [] : Arr::wrap($resolver($request));

        foreach ($limits as $limit) {
            $key = 'health-ready:' . $limit->key;

            if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
                return RateLimiter::availableIn($key);
            }

            RateLimiter::hit($key, $limit->decaySeconds);
        }

        return null;
    }

    private function logOnce(Throwable $e): void
    {
        if (self::$loggedFailure) {
            return;
        }

        self::$loggedFailure = true;
        $this->logger->warning('Health probe rate limiter unavailable, failing open', ['exception' => $e::class]);
    }
}
