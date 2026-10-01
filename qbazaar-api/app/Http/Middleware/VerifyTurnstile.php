<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Services\Auth\Turnstile\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards endpoints that trigger an SMS or email (register, OTP sends)
 * with a Cloudflare Turnstile token sent in the X-Turnstile-Token header.
 * Apply after the throttle middleware so rejected bots still count against
 * the rate limit without costing a siteverify call.
 */
class VerifyTurnstile
{
    public const HEADER = 'X-Turnstile-Token';

    private const MAX_TOKEN_LENGTH = 2048;

    public function __construct(
        private readonly TurnstileVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.turnstile.enabled')) {
            return $next($request);
        }

        $token = (string) $request->header(self::HEADER);

        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH
            || ! $this->verifier->verify($token, $request->ip())) {
            throw new DomainException(ErrorCode::TURNSTILE_FAILED);
        }

        return $next($request);
    }
}
