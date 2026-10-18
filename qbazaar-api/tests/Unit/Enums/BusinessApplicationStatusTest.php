<?php

declare(strict_types=1);

use App\Enums\BusinessApplicationStatus;

it('declares every allowed application transition and nothing else', function (): void {
    $allowed = [];

    foreach (BusinessApplicationStatus::cases() as $from) {
        foreach (BusinessApplicationStatus::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $allowed[] = "{$from->value}>{$to->value}";
            }
        }
    }

    expect($allowed)->toEqualCanonicalizing([
        'pending>needs_info',
        'pending>approved',
        'pending>rejected',
        'needs_info>pending',
        'needs_info>approved',
        'needs_info>rejected',
    ]);
});

it('treats only the statuses waiting for someone as open', function (): void {
    $open = array_values(array_filter(BusinessApplicationStatus::cases(), static fn (BusinessApplicationStatus $status): bool => $status->isOpen()));

    expect($open)->toBe([BusinessApplicationStatus::PENDING, BusinessApplicationStatus::NEEDS_INFO]);
});
