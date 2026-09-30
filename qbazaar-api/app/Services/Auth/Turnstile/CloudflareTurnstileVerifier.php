<?php

declare(strict_types=1);

namespace App\Services\Auth\Turnstile;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

/**
 * Fails closed: a missing secret or an unreachable siteverify endpoint
 * rejects the request, so a Cloudflare outage cannot become an open door
 * for SMS/email spam.
 */
final class CloudflareTurnstileVerifier implements TurnstileVerifier
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function verify(string $token, ?string $remoteIp): bool
    {
        $secret = (string) config('services.turnstile.secret');

        if ($secret === '') {
            Log::error('Turnstile is enabled but TURNSTILE_SECRET_KEY is not set.');

            return false;
        }

        try {
            $response = $this->http
                ->asForm()
                ->timeout((int) config('services.turnstile.timeout_seconds'))
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));
        } catch (ConnectionException $exception) {
            Log::warning('Turnstile siteverify is unreachable.', ['error' => $exception->getMessage()]);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
