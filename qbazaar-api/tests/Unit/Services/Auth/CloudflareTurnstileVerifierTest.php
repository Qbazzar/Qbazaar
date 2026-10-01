<?php

declare(strict_types=1);

use App\Services\Auth\Turnstile\CloudflareTurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.turnstile.secret' => 'test-secret']);
});

it('accepts a token cloudflare confirms and sends secret, token and ip as a form', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    expect(app(CloudflareTurnstileVerifier::class)->verify('token-1', '203.0.113.7'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request->isForm()
        && $request['secret'] === 'test-secret'
        && $request['response'] === 'token-1'
        && $request['remoteip'] === '203.0.113.7');
});

it('rejects a token cloudflare refuses', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect(app(CloudflareTurnstileVerifier::class)->verify('bad', null))->toBeFalse();
});

it('fails closed when siteverify returns a server error', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response('oops', 500)]);

    expect(app(CloudflareTurnstileVerifier::class)->verify('token', null))->toBeFalse();
});

it('fails closed when siteverify is unreachable', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => fn () => throw new ConnectionException('timeout')]);

    expect(app(CloudflareTurnstileVerifier::class)->verify('token', null))->toBeFalse();
});

it('fails closed without calling cloudflare when the secret is missing', function (): void {
    config(['services.turnstile.secret' => null]);
    Http::fake();

    expect(app(CloudflareTurnstileVerifier::class)->verify('token', null))->toBeFalse();

    Http::assertNothingSent();
});
