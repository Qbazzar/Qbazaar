<?php

declare(strict_types=1);

use App\Enums\SocialProvider;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Services\Auth\Social\JwksSocialTokenVerifier;
use App\Services\Auth\Social\SocialIdentity;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const GOOGLE_TEST_CLIENT_ID = 'web-client.apps.googleusercontent.com';

/**
 * Throwaway keys checked in under tests/Fixtures/jwt: generating RSA keys at
 * runtime needs an openssl.cnf that Windows PHP builds often lack.
 */
final class TestSigningKey
{
    public function __construct(
        public readonly string $kid,
        public readonly string $privatePem,
    ) {}

    public static function fixture(string $kid, int $fixture): self
    {
        return new self($kid, (string) file_get_contents(base_path('tests/Fixtures/jwt/test-rsa-' . $fixture . '.pem')));
    }

    /**
     * @return array<string, string>
     */
    public function jwk(): array
    {
        $key = openssl_pkey_get_private($this->privatePem);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if ($details === false) {
            throw new RuntimeException('Unreadable test key.');
        }

        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $this->kid,
            'n' => $encode($details['rsa']['n']),
            'e' => $encode($details['rsa']['e']),
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function sign(array $claims): string
    {
        return JWT::encode($claims, $this->privatePem, 'RS256', $this->kid);
    }
}

/**
 * The provider's key endpoint; tests change what it publishes mid-test.
 */
final class FakeJwksEndpoint
{
    /** @var list<array<string, string>> */
    public array $keys = [];

    public int $status = 200;

    public static function for(SocialProvider $provider, TestSigningKey $key): self
    {
        $endpoint = new self;
        $endpoint->keys = [$key->jwk()];

        Http::fake([$provider->jwksUrl() => fn () => Http::response(['keys' => $endpoint->keys], $endpoint->status)]);

        return $endpoint;
    }
}

/**
 * @param array<string, mixed> $overrides
 */
function googleIdToken(TestSigningKey $key, array $overrides = []): string
{
    return $key->sign([
        'iss' => 'https://accounts.google.com',
        'aud' => GOOGLE_TEST_CLIENT_ID,
        'sub' => '1098765',
        'email' => 'Owner@Example.qa',
        'email_verified' => true,
        'name' => 'Ahmed Al-Ali',
        'iat' => time(),
        'exp' => time() + 3600,
        ...$overrides,
    ]);
}

function verifyGoogle(string $token): SocialIdentity
{
    return app(JwksSocialTokenVerifier::class)->verify(SocialProvider::GOOGLE, $token);
}

function failsWith(ErrorCode $code): Closure
{
    return fn (DomainException $exception): bool => $exception->errorCode === $code;
}

beforeEach(function (): void {
    Cache::flush();
    config(['services.google.client_ids' => GOOGLE_TEST_CLIENT_ID . ', ios-client.apps.googleusercontent.com']);
});

it('returns the verified identity for a well-signed token', function (): void {
    $key = TestSigningKey::fixture('key-1', 1);
    FakeJwksEndpoint::for(SocialProvider::GOOGLE, $key);

    $identity = verifyGoogle(googleIdToken($key));

    expect($identity->provider)->toBe(SocialProvider::GOOGLE)
        ->and($identity->subject)->toBe('1098765')
        ->and($identity->email)->toBe('owner@example.qa')
        ->and($identity->name)->toBe('Ahmed Al-Ali');
});

it('caches the key set between sign-ins', function (): void {
    $key = TestSigningKey::fixture('key-1', 1);
    FakeJwksEndpoint::for(SocialProvider::GOOGLE, $key);

    verifyGoogle(googleIdToken($key));
    verifyGoogle(googleIdToken($key));

    Http::assertSentCount(1);
});

it('refetches the key set once when the provider rotated its keys', function (): void {
    $old = TestSigningKey::fixture('key-1', 1);
    $endpoint = FakeJwksEndpoint::for(SocialProvider::GOOGLE, $old);
    verifyGoogle(googleIdToken($old));

    $rotated = TestSigningKey::fixture('key-2', 2);
    $endpoint->keys = [$rotated->jwk()];

    expect(verifyGoogle(googleIdToken($rotated))->subject)->toBe('1098765');

    Http::assertSentCount(2);
});

it('rejects a token that fails a check', function (array $overrides): void {
    $key = TestSigningKey::fixture('key-1', 1);
    FakeJwksEndpoint::for(SocialProvider::GOOGLE, $key);

    expect(fn () => verifyGoogle(googleIdToken($key, $overrides)))
        ->toThrow(failsWith(ErrorCode::AUTH_SOCIAL_TOKEN_INVALID));
})->with([
    'other audience' => [['aud' => 'someone-else.apps.googleusercontent.com']],
    'other issuer' => [['iss' => 'https://evil.example.com']],
    'expired' => [['exp' => time() - 3600, 'iat' => time() - 7200]],
    'unverified email' => [['email_verified' => false]],
    'no email' => [['email' => null]],
]);

it('rejects a token signed by a key the provider never published', function (): void {
    FakeJwksEndpoint::for(SocialProvider::GOOGLE, TestSigningKey::fixture('key-1', 1));
    $stranger = TestSigningKey::fixture('key-1', 3);

    expect(fn () => verifyGoogle(googleIdToken($stranger)))
        ->toThrow(failsWith(ErrorCode::AUTH_SOCIAL_TOKEN_INVALID));
});

it('rejects garbage', function (): void {
    FakeJwksEndpoint::for(SocialProvider::GOOGLE, TestSigningKey::fixture('key-1', 1));

    expect(fn () => verifyGoogle('not.a.jwt'))->toThrow(failsWith(ErrorCode::AUTH_SOCIAL_TOKEN_INVALID));
});

it('is unavailable without configured client ids', function (): void {
    $key = TestSigningKey::fixture('key-1', 1);
    FakeJwksEndpoint::for(SocialProvider::GOOGLE, $key);
    config(['services.google.client_ids' => '']);

    expect(fn () => verifyGoogle(googleIdToken($key)))
        ->toThrow(failsWith(ErrorCode::AUTH_SOCIAL_PROVIDER_UNAVAILABLE));

    Http::assertNothingSent();
});

it('is unavailable when the key set cannot be fetched', function (): void {
    $key = TestSigningKey::fixture('key-1', 1);
    FakeJwksEndpoint::for(SocialProvider::GOOGLE, $key)->status = 503;

    expect(fn () => verifyGoogle(googleIdToken($key)))
        ->toThrow(failsWith(ErrorCode::AUTH_SOCIAL_PROVIDER_UNAVAILABLE));
});

it('accepts apple tokens whose email_verified claim is a string', function (): void {
    config(['services.apple.client_ids' => 'qa.qbazaar.app']);
    $key = TestSigningKey::fixture('apple-1', 2);
    FakeJwksEndpoint::for(SocialProvider::APPLE, $key);

    $token = $key->sign([
        'iss' => 'https://appleid.apple.com',
        'aud' => 'qa.qbazaar.app',
        'sub' => '001234.abcd',
        'email' => 'relay@privaterelay.appleid.com',
        'email_verified' => 'true',
        'iat' => time(),
        'exp' => time() + 600,
    ]);

    $identity = app(JwksSocialTokenVerifier::class)->verify(SocialProvider::APPLE, $token);

    expect($identity->subject)->toBe('001234.abcd')
        ->and($identity->name)->toBeNull();
});
