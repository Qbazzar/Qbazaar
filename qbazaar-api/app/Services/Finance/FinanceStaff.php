<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\FinanceReview;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\Finance\FinanceReviewRequestedNotification;
use App\Services\Admin\StaffDirectory;
use Illuminate\Support\Facades\Notification;

/**
 * The staff who act on money: everyone active with `finance.manage`.
 */
class FinanceStaff
{
    public const string MANAGE_PERMISSION = 'finance.manage';

    public function __construct(
        private readonly StaffDirectory $staff,
    ) {}

    /**
     * Tells them a settlement, withdrawal or dispute is waiting. Each
     * delivery is queued, so the caller's request does not wait on mail.
     */
    public function requestReview(FinanceReview $review, string $amount): void
    {
        $recipients = User::query()
            ->whereIn('id', $this->staff->idsWithPermission(self::MANAGE_PERMISSION))
            ->where('status', UserStatus::ACTIVE->value)
            ->get();

        Notification::send($recipients, new FinanceReviewRequestedNotification($review, $amount));
    }
}
