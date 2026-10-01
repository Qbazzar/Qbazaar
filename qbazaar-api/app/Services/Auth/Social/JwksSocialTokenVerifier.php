<?php

declare(strict_types=1);

namespace App\Services\Auth\Social;

use App\Enums\SocialProvider;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies an OpenID Connect id_token against the provider's published keys:
 * RS256 signature, issuer, audience (one of our client ids), expiry, and a
 * verified email.
 *
 * The key set is cached. A token signed with a key we have not cached yet
 * (the provider rotated) forces one refetch, at most once a minute, so
 * garbage tokens cannot turn us into a request pump against the provider.
 */
final class JwksSocialTokenVerifier implements SocialTokenVerifier
{
    private const REFETCH_GUARD_SECONDS = 60;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function verify(SocialProvider $provider, string $idToken): SocialIdentity
    {
        $audiences = $provider->clientIds();

        if ($audiences === []) {
            throw new DomainException(ErrorCode::AUTH_SOCIAL_PROVIDER_UNAVAILABLE);
        }

        $claims = $this->decode($provider, $idToken);

        $audience = $claims['aud'] ?? null;
        $tokenAudiences = is_array($audience) ? $audience : [$audience];
        $email = $claims['email'] ?? null;
        $subject = $claims['sub'] ?? null;

        $valid = in_array($claims['iss'] ?? null, $provider->issuers(), true)
            && array_intersect($tokenAudiences, $audiences) !== []
            && is_string($subject) && $subject !== ''
            && is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && $this->isTrue($claims['email_verified'] ?? null);

        if (! $valid) {
            throw new DomainException(ErrorCode::AUTH_SOCIAL_TOKEN_INVALID);
        }

        $name = $claims['name'] ?? null;

        return new SocialIdentity(
            provider: $provider,
            subject: $subject,
            email: strtolower($email),
            name: is_string($name) && $name !== '' ? $name : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(SocialProvider $provider, string $idToken): array
    {
        $keys = $this->keys($provider);
        $kid = $this->keyIdOf($idToken);

        if ($kid !== null && ! isset($keys[$kid])
            && Cache::add($this->cacheKey($provider) . ':refetch', true, self::REFETCH_GUARD_SECONDS)) {
            Cache::forget($this->cacheKey($provider));
            $keys = $this->keys($provider);
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = (int) config('qbazaar.auth.social.clock_leeway_seconds');

        try {
            return (array) JWT::decode($idToken, $keys);
        } catch (Throwable) {
            throw new DomainException(ErrorCode::AUTH_SOCIAL_TOKEN_INVALID);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * @return array<string, Key>
     */
    private function keys(SocialProvider $provider): array
    {
        $ttl = (int) config('qbazaar.auth.social.jwks_cache_minutes') * 60;

        try {
            /** @var array{keys: list<array<string, mixed>>} $jwks */
            $jwks = Cache::remember($this->cacheKey($provider), $ttl, fn (): array => $this->http
                ->timeout(5)
                ->get($provider->jwksUrl())
                ->throw()
                ->json());

            return JWK::parseKeySet($jwks, 'RS256');
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Social sign-in key set is unreachable.', [
                'provider' => $provider->value,
                'error' => $exception->getMessage(),
            ]);

            throw new DomainException(ErrorCode::AUTH_SOCIAL_PROVIDER_UNAVAILABLE);
        } catch (Throwable) {
            Cache::forget($this->cacheKey($provider));

            throw new DomainException(ErrorCode::AUTH_SOCIAL_PROVIDER_UNAVAILABLE);
        }
    }

    private function keyIdOf(string $idToken): ?string
    {
        $header = explode('.', $idToken)[0];

        try {
            $decoded = JWT::jsonDecode(JWT::urlsafeB64Decode($header));
        } catch (Throwable) {
            return null;
        }

        return is_object($decoded) && isset($decoded->kid) && is_string($decoded->kid) ? $decoded->kid : null;
    }

    private function isTrue(mixed $value): bool
    {
        return $value === true || $value === 'true';
    }

    private function cacheKey(SocialProvider $provider): string
    {
        return 'auth:social-jwks:' . $provider->value;
    }
}
