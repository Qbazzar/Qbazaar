<?php

declare(strict_types=1);

use App\Services\Admin\TwoFactor\Totp;
use App\Support\Base32;

// RFC 6238 appendix B, SHA-1 key "12345678901234567890", last six digits.
dataset('rfc 6238 vectors', [
    'T=59' => [59, '287082'],
    'T=1111111109' => [1111111109, '081804'],
    'T=1111111111' => [1111111111, '050471'],
    'T=1234567890' => [1234567890, '005924'],
    'T=2000000000' => [2000000000, '279037'],
    'T=20000000000' => [20000000000, '353130'],
]);

function rfcSecret(): string
{
    return Base32::encode('12345678901234567890');
}

it('matches the RFC 6238 test vectors', function (int $timestamp, string $code): void {
    expect(app(Totp::class)->codeFor(rfcSecret(), $timestamp))->toBe($code);
})->with('rfc 6238 vectors');

it('returns the matched time step, inside the drift window only', function (): void {
    $totp = app(Totp::class);
    $now = 1111111111;
    $previous = $totp->codeFor(rfcSecret(), $now - Totp::PERIOD);
    $older = $totp->codeFor(rfcSecret(), $now - 3 * Totp::PERIOD);

    expect($totp->verify(rfcSecret(), $previous, $now, 1))->toBe(intdiv($now, Totp::PERIOD) - 1)
        ->and($totp->verify(rfcSecret(), $older, $now, 1))->toBeNull();
});

it('never accepts a step at or before the last one used', function (): void {
    $totp = app(Totp::class);
    $now = 1234567890;
    $code = $totp->codeFor(rfcSecret(), $now);
    $step = intdiv($now, Totp::PERIOD);

    expect($totp->verify(rfcSecret(), $code, $now, 1, $step))->toBeNull()
        ->and($totp->verify(rfcSecret(), $code, $now, 1, $step - 1))->toBe($step);
});

it('rejects codes that are not six digits and secrets that are not base32', function (string $code): void {
    expect(app(Totp::class)->verify(rfcSecret(), $code, 59))->toBeNull();
})->with(['28708', '2870821', '28708a', '', ' 287082']);

it('treats a corrupt secret as a failed check, not an error', function (): void {
    expect(app(Totp::class)->verify('not-base32!', '287082', 59))->toBeNull();
});

it('generates 160-bit secrets that round-trip through base32', function (): void {
    $secret = app(Totp::class)->generateSecret();

    expect(strlen(Base32::decode($secret)))->toBe(20)
        ->and(Base32::encode(Base32::decode($secret)))->toBe($secret)
        ->and(app(Totp::class)->generateSecret())->not->toBe($secret);
});

it('builds an otpauth URI authenticator apps import', function (): void {
    $uri = app(Totp::class)->provisioningUri('QBazaar Admin', 'staff@qbazaar.qa', 'ABCDEF');

    expect($uri)->toStartWith('otpauth://totp/QBazaar%20Admin:staff%40qbazaar.qa?')
        ->toContain('secret=ABCDEF')
        ->toContain('issuer=QBazaar%20Admin')
        ->toContain('digits=6')
        ->toContain('period=30');
});
