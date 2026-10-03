<?php

declare(strict_types=1);

use App\Enums\OrderStatus;

it('declares every allowed order transition and nothing else', function (): void {
    $allowed = [];

    foreach (OrderStatus::cases() as $from) {
        foreach (OrderStatus::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $allowed[] = "{$from->value}>{$to->value}";
            }
        }
    }

    expect($allowed)->toEqualCanonicalizing([
        'created>awaiting_handover',
        'created>cancelled',
        'awaiting_handover>completed',
        'awaiting_handover>cancelled',
        'awaiting_handover>disputed',
        'disputed>completed',
        'disputed>cancelled',
    ]);
});

it('treats created, awaiting handover and disputed orders as holding the ad', function (): void {
    expect(OrderStatus::activeValues())->toEqualCanonicalizing(['created', 'awaiting_handover', 'disputed']);
});
