<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Enums\BusinessApplicationStatus;
use App\Enums\BusinessNotice;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\BusinessApplication;
use App\Models\User;
use App\Notifications\Business\BusinessAccountNotification;
use App\Services\Admin\AdminAuditLogger;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Staff decide on a business application: approve it (the account becomes
 * a verified business and its business profile takes the declared legal
 * details), reject it with a reason, or ask the applicant for more
 * information. The application row is locked first and only an open one
 * can be decided, so a double click or two reviewers at once never decide
 * twice. Each decision is audited and the applicant is notified.
 */
class BusinessApplicationReview
{
    public function __construct(
        private readonly CompanyVerification $verification,
        private readonly BusinessApplicationQueue $queue,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function approve(User $admin, BusinessApplication $application, ?string $note): BusinessApplication
    {
        $approved = $this->decide($admin, $application, BusinessApplicationStatus::APPROVED, $note, function (BusinessApplication $locked) use ($admin): void {
            $applicant = $locked->user ?? throw new DomainException(ErrorCode::USER_NOT_FOUND);

            if (! $applicant->isVerifiedBusiness()) {
                $this->verification->grant($admin, $applicant, convertToBusiness: true);
            }

            $applicant->businessProfile()->firstOrCreate();
            $applicant->businessProfile()->update([
                'legal_name' => $locked->legal_name,
                'commercial_registration_number' => $locked->commercial_registration_number,
            ]);
            $applicant->businessProfile()->whereNull('business_name')->update(['business_name' => $locked->business_name]);
        });

        $this->notify($approved, BusinessNotice::APPLICATION_APPROVED);

        return $approved;
    }

    public function reject(User $admin, BusinessApplication $application, string $reason): BusinessApplication
    {
        $rejected = $this->decide($admin, $application, BusinessApplicationStatus::REJECTED, $reason);

        $this->notify($rejected, BusinessNotice::APPLICATION_REJECTED);

        return $rejected;
    }

    public function requestInfo(User $admin, BusinessApplication $application, string $message): BusinessApplication
    {
        $waiting = $this->decide($admin, $application, BusinessApplicationStatus::NEEDS_INFO, $message);

        $this->notify($waiting, BusinessNotice::APPLICATION_NEEDS_INFO);

        return $waiting;
    }

    /**
     * @param (Closure(BusinessApplication): void)|null $apply extra writes inside the same transaction
     */
    private function decide(User $admin, BusinessApplication $application, BusinessApplicationStatus $next, ?string $note, ?Closure $apply = null): BusinessApplication
    {
        $decided = DB::transaction(function () use ($admin, $application, $next, $note, $apply): BusinessApplication {
            /** @var BusinessApplication $locked */
            $locked = BusinessApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($next)) {
                throw new DomainException(ErrorCode::BUSINESS_APPLICATION_NOT_OPEN);
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->forceFill([
                'status' => $next,
                'review_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $locked;
        });

        $this->audit->record($admin, 'admin.business-applications.' . $this->eventOf($next), $decided, [
            'note' => $note,
            'applicant_id' => $decided->user_id,
        ]);
        $this->queue->forgetPendingCount();

        return $decided;
    }

    private function eventOf(BusinessApplicationStatus $status): string
    {
        return match ($status) {
            BusinessApplicationStatus::APPROVED => 'approve',
            BusinessApplicationStatus::REJECTED => 'reject',
            BusinessApplicationStatus::NEEDS_INFO => 'request-info',
            BusinessApplicationStatus::PENDING => 'resubmitted',
        };
    }

    private function notify(BusinessApplication $application, BusinessNotice $notice): void
    {
        $application->user?->notify(new BusinessAccountNotification($notice, $application->review_note, $application->id));
    }
}
