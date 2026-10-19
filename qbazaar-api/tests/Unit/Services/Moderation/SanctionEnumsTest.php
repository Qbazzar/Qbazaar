<?php

declare(strict_types=1);

use App\Data\Moderation\SanctionReasonInput;
use App\Enums\SanctionAction;
use App\Enums\SanctionReason;

it('asks for a note only with "other"', function (): void {
    $needNote = array_filter(SanctionReason::cases(), fn (SanctionReason $reason): bool => $reason->requiresNote());

    expect(array_values($needNote))->toBe([SanctionReason::OTHER]);
});

it('words every reason for staff and for users in both languages', function (SanctionReason $reason, string $locale): void {
    expect($reason->adminLabel($locale))->not->toStartWith('admin.')
        ->and($reason->userLabel($locale))->not->toStartWith('messages.');
})->with(fn (): array => array_map(fn (SanctionReason $reason): array => [$reason], SanctionReason::cases()))->with(['ar', 'en']);

it('ties each sanction to the permission and audit event of its own route', function (): void {
    expect(SanctionAction::AD_SUSPENDED->permission())->toBe('ads.suspend')
        ->and(SanctionAction::AD_REMOVED->permission())->toBe('ads.delete')
        ->and(SanctionAction::USER_SUSPENDED->permission())->toBe('users.ban')
        ->and(SanctionAction::AD_SUSPENDED->auditEvent())->toBe('admin.ads.suspend')
        ->and(SanctionAction::AD_REMOVED->auditEvent())->toBe('admin.ads.destroy')
        ->and(SanctionAction::USER_SUSPENDED->auditEvent())->toBe('admin.users.suspend')
        ->and(SanctionAction::USER_SUSPENDED->subjectType())->toBe(SanctionAction::SUBJECT_USER)
        ->and(SanctionAction::AD_REMOVED->subjectType())->toBe(SanctionAction::SUBJECT_AD);
});

it('summarises a reason, with its note, for staff records and the audit', function (): void {
    app()->setLocale('en');

    $withNote = new SanctionReasonInput(SanctionReason::SPAM, 'Posted 12 times today');
    $bare = new SanctionReasonInput(SanctionReason::DUPLICATE);

    expect($withNote->summary())->toBe(SanctionReason::SPAM->adminLabel() . ' — Posted 12 times today')
        ->and($bare->summary())->toBe(SanctionReason::DUPLICATE->adminLabel())
        ->and($withNote->toAudit())->toBe(['reason' => 'spam', 'note' => 'Posted 12 times today']);
});
