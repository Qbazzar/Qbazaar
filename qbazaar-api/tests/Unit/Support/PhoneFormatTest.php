<?php

declare(strict_types=1);

use App\Support\PhoneFormat;

it('shows a dash for a missing phone', function (?string $phone): void {
    expect(PhoneFormat::display($phone))->toBe('—');
})->with(fn (): array => [[null], [''], ['   ']]);

it('groups Qatari numbers whatever their prefix style', function (string $phone): void {
    expect(PhoneFormat::display($phone))->toBe('+974 3900 0000');
})->with(['+97439000000', '97439000000', '0097439000000', '+974 3900 0000']);

it('leaves foreign numbers as stored', function (): void {
    expect(PhoneFormat::display('+447911123456'))->toBe('+447911123456');
});
