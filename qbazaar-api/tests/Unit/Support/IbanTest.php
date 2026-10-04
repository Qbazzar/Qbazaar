<?php

declare(strict_types=1);

use App\Support\Iban;

it('accepts valid IBANs written with spaces, dashes or lower case', function (string $iban): void {
    expect(Iban::isValid($iban))->toBeTrue();
})->with([
    'Qatar' => 'QA58 DOHB 0000 1234 5678 90AB CDEF G',
    'Saudi Arabia' => 'SA03 8000 0000 6080 1016 7519',
    'United Kingdom, lower case' => 'gb82west12345698765432',
    'dashes' => 'GB82-WEST-1234-5698-7654-32',
]);

it('rejects malformed IBANs and wrong checksums', function (string $iban): void {
    expect(Iban::isValid($iban))->toBeFalse();
})->with([
    'one digit changed' => 'QA58DOHB00001234567890ABCDEFH',
    'two digits swapped' => 'GB82WEST12345698765423',
    'wrong length for Qatar' => 'QA58DOHB00001234567890ABCDEF',
    'no country' => '5800001234567890ABCDEFG',
    'symbols' => 'GB82WEST1234569876543!',
    'empty' => '',
]);

it('normalizes and masks to the country and the last four characters', function (): void {
    $iban = Iban::normalize('qa58 dohb 0000 1234 5678 90ab cdef g');

    expect($iban)->toBe('QA58DOHB00001234567890ABCDEFG')
        ->and(Iban::lastFour($iban))->toBe('DEFG')
        ->and(Iban::mask('QA', 'DEFG'))->toBe('QA** **** DEFG');
});
