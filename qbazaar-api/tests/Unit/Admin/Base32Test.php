<?php

declare(strict_types=1);

use App\Support\Base32;

dataset('rfc 4648 vectors', [
    'empty' => ['', ''],
    'f' => ['f', 'MY'],
    'fo' => ['fo', 'MZXQ'],
    'foo' => ['foo', 'MZXW6'],
    'foob' => ['foob', 'MZXW6YQ'],
    'fooba' => ['fooba', 'MZXW6YTB'],
    'foobar' => ['foobar', 'MZXW6YTBOI'],
]);

it('encodes the RFC 4648 test vectors without padding', function (string $plain, string $encoded): void {
    expect(Base32::encode($plain))->toBe($encoded);
})->with('rfc 4648 vectors');

it('decodes the RFC 4648 test vectors, padded or not, in any case and grouping', function (string $plain, string $encoded): void {
    expect(Base32::decode($encoded))->toBe($plain)
        ->and(Base32::decode(strtolower($encoded)))->toBe($plain)
        ->and(Base32::decode(str_pad($encoded, (int) ceil(strlen($encoded) / 8) * 8, '=')))->toBe($plain)
        ->and(Base32::decode(implode(' ', str_split($encoded, 4) ?: [''])))->toBe($plain);
})->with('rfc 4648 vectors');

it('round-trips random binary keys', function (): void {
    foreach (range(1, 25) as $length) {
        $bytes = random_bytes($length);

        expect(Base32::decode(Base32::encode($bytes)))->toBe($bytes);
    }
});

it('refuses characters outside the alphabet', function (string $encoded): void {
    expect(fn () => Base32::decode($encoded))->toThrow(InvalidArgumentException::class);
})->with(['MZ1W', 'MZ8W', 'MZ!W', 'MZXÜ']);
