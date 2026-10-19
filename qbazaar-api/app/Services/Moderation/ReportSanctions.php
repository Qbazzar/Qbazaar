<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Actions\Reports\ResolveReportAction;
use App\Data\Moderation\SanctionReasonInput;
use App\Enums\AdStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Enums\SanctionAction;
use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Message;
use App\Models\ModerationSanction;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\StaffHierarchy;
use Illuminate\Support\Facades\DB;

/**
 * Acting on a report in one step: sanction its target (suspend or remove the
 * ad, suspend the ad's owner, the reported account or a message's sender) and
 * close the report as actioned, in one transaction.
 *
 * `availableFor()` is what the report page offers; `apply()` checks the same
 * list again, and the report's status on its locked row, so a stale page
 * cannot run a move that no longer fits (an ad suspended meanwhile, a report
 * another moderator closed, a permission withdrawn).
 */
class ReportSanctions
{
    public const string AUDIT_EVENT = 'admin.reports.sanction';

    public function __construct(
        private readonly SanctionService $sanctions,
        private readonly ResolveReportAction $resolveReport,
        private readonly StaffHierarchy $hierarchy,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param ReportTargets|null $targets the targets when the caller already loaded them, read here otherwise
     * @return list<SanctionAction>
     */
    public function availableFor(Report $report, User $staff, ?ReportTargets $targets = null): array
    {
        if ($report->status !== ReportStatus::PENDING || ! $staff->can('reports.action')) {
            return [];
        }

        return $this->actionsOn($targets ?? $this->targetsOf($report), $staff);
    }

    public function apply(User $staff, Report $report, SanctionAction $action, SanctionReasonInput $input): ModerationSanction
    {
        $targets = $this->targetsOf($report);

        // Reaching above one's own rank is refused outright (403), as on the account page.
        if ($action === SanctionAction::USER_SUSPENDED && $targets->account !== null) {
            $this->hierarchy->ensureCanManage($staff, $targets->account);
        }

        if ($report->status !== ReportStatus::PENDING
            || ! $staff->can('reports.action')
            || ! in_array($action, $this->actionsOn($targets, $staff), true)) {
            throw $this->unavailable();
        }

        return DB::transaction(function () use ($staff, $report, $action, $input, $targets): ModerationSanction {
            $locked = Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReportStatus::PENDING) {
                throw $this->unavailable();
            }

            $sanction = match ($action) {
                SanctionAction::AD_SUSPENDED => $this->sanctions->suspendAd($staff, $this->required($targets->ad), $input, $locked),
                SanctionAction::AD_REMOVED => $this->sanctions->removeAd($staff, $this->required($targets->ad), $input, $locked),
                SanctionAction::USER_SUSPENDED => $this->sanctions->suspendUser($staff, $this->required($targets->account), $input, $locked),
            };

            $this->resolveReport->execute($locked, ReportStatus::ACTIONED, $staff, $action->label() . ': ' . $input->summary());
            $this->audit->record($staff, self::AUDIT_EVENT, $locked, [
                'action' => $action->value,
                ...$input->toAudit(),
                'sanction_id' => $sanction->id,
            ]);

            return $sanction;
        });
    }

    /**
     * @return list<SanctionAction>
     */
    private function actionsOn(ReportTargets $targets, User $staff): array
    {
        $ad = $targets->ad;
        $account = $targets->account;
        $actions = [];

        if ($ad !== null && $ad->status === AdStatus::ACTIVE && $staff->can(SanctionAction::AD_SUSPENDED->permission())) {
            $actions[] = SanctionAction::AD_SUSPENDED;
        }
        if ($ad !== null && $staff->can(SanctionAction::AD_REMOVED->permission())) {
            $actions[] = SanctionAction::AD_REMOVED;
        }
        if ($account !== null
            && $account->status !== UserStatus::SUSPENDED
            && $staff->can(SanctionAction::USER_SUSPENDED->permission())
            && $this->hierarchy->canManage($staff, $account)) {
            $actions[] = SanctionAction::USER_SUSPENDED;
        }

        return $actions;
    }

    /**
     * The reported ad (live ones only: a deleted ad cannot be sanctioned again)
     * and the account a USER_SUSPENDED sanction would hit: the reported user,
     * the reported ad's owner, or the sender of a reported message. A reported
     * conversation has two parties, so it names no one.
     */
    private function targetsOf(Report $report): ReportTargets
    {
        $ad = $report->target_type === ReportTarget::AD
            ? Ad::query()->with('user')->find($report->target_id)
            : null;

        $account = match ($report->target_type) {
            ReportTarget::USER => User::query()->find($report->target_id),
            ReportTarget::AD => $ad?->user,
            ReportTarget::MESSAGE => $this->messageSender($report->target_id),
            ReportTarget::CONVERSATION => null,
        };

        return new ReportTargets($ad, $account);
    }

    private function messageSender(string $messageId): ?User
    {
        $senderId = Message::query()->whereKey($messageId)->value('sender_id');

        return $senderId === null ? null : User::query()->whereKey($senderId)->first();
    }

    /**
     * @template TModel of Ad|User
     *
     * @param TModel|null $model
     * @return TModel
     */
    private function required(Ad|User|null $model): Ad|User
    {
        return $model ?? throw $this->unavailable();
    }

    private function unavailable(): DomainException
    {
        return new DomainException(ErrorCode::VALIDATION_FAILED, (string) __('admin.moderation.errors.action_unavailable'));
    }
}
