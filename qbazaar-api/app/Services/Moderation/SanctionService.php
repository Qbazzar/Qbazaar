<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Data\Moderation\SanctionReasonInput;
use App\Enums\SanctionAction;
use App\Events\Ads\AdSuspended;
use App\Events\Users\UserSuspended;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\ModerationSanction;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Ads\AdLifecycleService;
use App\Services\Users\UserModerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place staff suspend or remove an ad, or suspend an account. Each
 * move runs in one transaction with its sanction row and its audit entry, and
 * the AdSuspended / UserSuspended events (which tell the affected user) only
 * fire once it is committed.
 *
 * Status changes stay with their owners: AdLifecycleService for ads and
 * UserModerationService (staff hierarchy, session revocation) for accounts.
 */
class SanctionService
{
    public function __construct(
        private readonly AdLifecycleService $ads,
        private readonly UserModerationService $users,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function suspendAd(User $staff, Ad $ad, SanctionReasonInput $input, ?Report $report = null): ModerationSanction
    {
        return DB::transaction(function () use ($staff, $ad, $input, $report): ModerationSanction {
            $this->ads->block($ad);

            return $this->record($staff, SanctionAction::AD_SUSPENDED, $ad, $input, $report);
        });
    }

    /**
     * The ad is read again on its locked row, so when two staff remove it at
     * once the second is refused instead of sanctioning the seller twice.
     */
    public function removeAd(User $staff, Ad $ad, SanctionReasonInput $input, ?Report $report = null): ModerationSanction
    {
        return DB::transaction(function () use ($staff, $ad, $input, $report): ModerationSanction {
            /** @var Ad|null $locked */
            $locked = Ad::query()->whereKey($ad->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new DomainException(ErrorCode::AD_NOT_FOUND, (string) __('admin.moderation.errors.ad_already_removed'));
            }

            if ($ad->relationLoaded('user')) {
                $locked->setRelation('user', $ad->user);
            }

            $locked->delete();
            $ad->setRawAttributes($locked->getAttributes(), true);

            return $this->record($staff, SanctionAction::AD_REMOVED, $locked, $input, $report);
        });
    }

    /**
     * Removes the given ads with one shared reason. The sanction rows go in
     * one insert; the deletes stay per model so search and the activity log
     * see each one, as on the single-delete path.
     *
     * The selection is read on locked rows, so an ad another staff member
     * removed meanwhile is skipped rather than sanctioned twice.
     *
     * @param list<string> $adIds bounded by the caller (bulk_action_max)
     * @return int how many ads this call removed
     */
    public function removeAds(User $staff, array $adIds, SanctionReasonInput $input): int
    {
        if ($adIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($staff, $adIds, $input): int {
            $ads = Ad::query()->with('user')->whereKey($adIds)->lockForUpdate()->get();
            $removals = [];
            foreach ($ads as $ad) {
                $removals[] = [$ad, $this->newSanction($staff, SanctionAction::AD_REMOVED, $ad, $input, null)];
            }

            $ads->each->delete();
            ModerationSanction::query()->insert(array_map(
                static fn (array $removal): array => $removal[1]->getAttributes(),
                $removals,
            ));

            foreach ($removals as [$ad, $sanction]) {
                $sanction->exists = true;
                $this->audit($staff, $sanction, $ad, $input);
                AdSuspended::dispatch($ad, $sanction);
            }

            return $ads->count();
        });
    }

    public function suspendUser(User $staff, User $user, SanctionReasonInput $input, ?Report $report = null): ModerationSanction
    {
        return DB::transaction(function () use ($staff, $user, $input, $report): ModerationSanction {
            $this->users->suspend($staff, $user);

            return $this->record($staff, SanctionAction::USER_SUSPENDED, $user, $input, $report);
        });
    }

    /**
     * The newest sanction on an ad or account, for the "why" line on its admin page.
     */
    public function latestFor(Ad|User $subject): ?ModerationSanction
    {
        return ModerationSanction::query()
            ->forSubject($subject instanceof Ad ? SanctionAction::SUBJECT_AD : SanctionAction::SUBJECT_USER, (string) $subject->getKey())
            ->with(['staff:id,full_name,email', 'staff.avatarMedia'])
            ->first();
    }

    private function record(User $staff, SanctionAction $action, Ad|User $subject, SanctionReasonInput $input, ?Report $report): ModerationSanction
    {
        $sanction = $this->newSanction($staff, $action, $subject, $input, $report);
        $sanction->save();

        $this->audit($staff, $sanction, $subject, $input);

        $subject instanceof Ad
            ? AdSuspended::dispatch($subject, $sanction)
            : UserSuspended::dispatch($subject, $sanction);

        return $sanction;
    }

    private function newSanction(User $staff, SanctionAction $action, Ad|User $subject, SanctionReasonInput $input, ?Report $report): ModerationSanction
    {
        $sanction = new ModerationSanction([
            'subject_type' => $action->subjectType(),
            'subject_id' => (string) $subject->getKey(),
            'action' => $action,
            'reason' => $input->reason,
            'note' => $input->note,
            'staff_id' => $staff->id,
            'report_id' => $report?->id,
        ]);
        $sanction->forceFill(['id' => strtolower((string) Str::ulid()), 'created_at' => now()]);

        return $sanction;
    }

    private function audit(User $staff, ModerationSanction $sanction, Ad|User $subject, SanctionReasonInput $input): void
    {
        $this->audit->record($staff, $sanction->action->auditEvent(), $subject, [
            ...$input->toAudit(),
            'sanction_id' => $sanction->id,
            'report_id' => $sanction->report_id,
        ]);
    }
}
