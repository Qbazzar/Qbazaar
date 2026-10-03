<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Reports\MakeReportAction;
use App\Actions\Reports\ResolveReportAction;
use App\Actions\Support\ChangeTicketStatusAction;
use App\Actions\Support\ReplyToTicketAsOwnerAction;
use App\Actions\Support\ReplyToTicketAsStaffAction;
use App\Actions\Support\SubmitSupportTicketAction;
use App\Enums\AdStatus;
use App\Enums\MessageType;
use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Enums\StaffRole;
use App\Enums\SupportTicketStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Users\UserModerationService;
use Database\Seeders\Demo\Catalog\Phrases;
use Database\Seeders\Demo\DemoContext;
use Illuminate\Support\Carbon;

/**
 * The back office: reports in every state, support tickets with replies,
 * a few suspended members, and the audit trail the staff actions leave.
 */
final class SeedModeration
{
    private const array REPORT_OUTCOMES = [
        ReportStatus::PENDING, ReportStatus::PENDING, ReportStatus::REVIEWED,
        ReportStatus::DISMISSED, ReportStatus::ACTIONED, ReportStatus::PENDING,
    ];

    private const array TICKET_STATUSES = [
        SupportTicketStatus::OPEN, SupportTicketStatus::IN_PROGRESS, SupportTicketStatus::WAITING_USER,
        SupportTicketStatus::RESOLVED, SupportTicketStatus::CLOSED,
    ];

    private const float SUSPENDED_SHARE = 0.05;

    public function __construct(
        private readonly MakeReportAction $makeReport,
        private readonly ResolveReportAction $resolveReport,
        private readonly SubmitSupportTicketAction $submitTicket,
        private readonly ReplyToTicketAsStaffAction $staffReply,
        private readonly ReplyToTicketAsOwnerAction $ownerReply,
        private readonly ChangeTicketStatusAction $changeTicketStatus,
        private readonly UserModerationService $userModeration,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function run(DemoContext $context): void
    {
        $this->seedReports($context);
        $this->seedTickets($context);
        $this->suspendSomeMembers($context);
    }

    private function seedReports(DemoContext $context): void
    {
        $targets = $this->reportTargets($context);
        $count = max(count(self::REPORT_OUTCOMES), intdiv($context->options->ads, 15));

        foreach (array_slice($targets, 0, $count) as $index => [$type, $targetId, $ownerId]) {
            $reporter = $context->memberOtherThan($ownerId);
            $category = $context->random->pick(ReportCategory::cases());
            $reportedAt = $context->clock->daysAgo($context->random->int(0, 10), $context->random->int(0, 600));
            $description = Phrases::REPORTS[$category->value][$reporter->language->value];

            $report = $context->clock->at($reportedAt, fn () => $this->makeReport->execute($reporter, $type, $targetId, $category, $description));
            $outcome = self::REPORT_OUTCOMES[$index % count(self::REPORT_OUTCOMES)];

            if ($outcome !== ReportStatus::PENDING) {
                $context->clock->at($reportedAt->copy()->addHours($context->random->int(2, 30)), function () use ($report, $outcome, $context): void {
                    $this->resolveReport->execute($report, $outcome, $context->moderator(), 'Checked by the moderation team.');
                    $this->audit->record($context->moderator(), 'admin.reports.' . ($outcome === ReportStatus::DISMISSED ? 'dismiss' : 'resolve'), $report);
                });
            }
        }
    }

    /**
     * Listings, members, conversations and messages, mixed so every target
     * type is reported at least once.
     *
     * @return list<array{ReportTarget, string, string}> target type, target id, the owner who must not report it
     */
    private function reportTargets(DemoContext $context): array
    {
        $ads = collect($context->random->sample(Ad::query()->where('status', AdStatus::ACTIVE->value)->get(['id', 'user_id'])->all(), 40));
        $conversations = collect($context->random->sample(Conversation::query()->get(['id', 'seller_id'])->all(), 5));
        $messages = collect($context->random->sample(Message::query()->where('type', MessageType::TEXT->value)->limit(200)->get(['id', 'sender_id'])->all(), 5));

        $targets = [
            ...$ads->take(1)->map(fn (Ad $ad): array => [ReportTarget::AD, $ad->id, $ad->user_id])->all(),
            ...$context->random->sample(array_map(fn (User $user): array => [ReportTarget::USER, $user->id, $user->id], $context->members), 3),
            ...$conversations->map(fn (Conversation $conversation): array => [ReportTarget::CONVERSATION, $conversation->id, $conversation->seller_id])->all(),
            ...$messages->map(fn (Message $message): array => [ReportTarget::MESSAGE, $message->id, $message->sender_id])->all(),
            ...$ads->skip(1)->map(fn (Ad $ad): array => [ReportTarget::AD, $ad->id, $ad->user_id])->all(),
        ];

        return array_values($targets);
    }

    private function seedTickets(DemoContext $context): void
    {
        $count = max(count(self::TICKET_STATUSES), intdiv(count($context->members), 5));
        $agent = $context->staffMember(StaffRole::SUPPORT);

        for ($index = 0; $index < $count; $index++) {
            $owner = $context->random->pick($context->members);
            $topic = $context->random->pick(Phrases::TICKETS);
            $language = $owner->language->value;
            [$subject, $body] = $topic[$language];
            $openedAt = $context->clock->daysAgo($context->random->int(0, 14), $context->random->int(0, 600));

            $ticket = $context->clock->at($openedAt, fn () => ($this->submitTicket)($owner, [
                'subject' => $subject,
                'category' => $topic['category']->value,
                'body' => $body,
            ]));

            $this->advanceTicket($context, $ticket, self::TICKET_STATUSES[$index % count(self::TICKET_STATUSES)], $owner, $agent, $openedAt);
        }
    }

    /**
     * Staff replies move a ticket open → in progress → waiting for the user;
     * resolved and closed tickets are set by the agent at the end.
     */
    private function advanceTicket(DemoContext $context, SupportTicket $ticket, SupportTicketStatus $target, User $owner, User $agent, Carbon $openedAt): void
    {
        $language = $owner->language->value;
        $at = $openedAt->copy();
        $staffReplies = match ($target) {
            SupportTicketStatus::OPEN => 0,
            SupportTicketStatus::IN_PROGRESS => 1,
            default => 2,
        };

        for ($reply = 0; $reply < $staffReplies; $reply++) {
            $at = $at->copy()->addHours($context->random->int(1, 12));
            $context->clock->at($at, fn () => ($this->staffReply)($ticket, $agent, Phrases::STAFF_REPLIES[$language][$reply]));
        }

        if ($target === SupportTicketStatus::RESOLVED || $target === SupportTicketStatus::CLOSED) {
            $at = $at->copy()->addHours($context->random->int(1, 12));
            $context->clock->at($at, fn () => ($this->ownerReply)($ticket, $owner, Phrases::OWNER_FOLLOW_UPS[$language]));
            $context->clock->at($at->copy()->addHour(), fn () => ($this->changeTicketStatus)($ticket, $target));
            $this->audit->record($agent, 'admin.support.status', $ticket, ['input' => ['status' => $target->value]]);
        }
    }

    private function suspendSomeMembers(DemoContext $context): void
    {
        $candidates = array_slice($context->members, 2);
        $count = max(1, (int) round(count($context->members) * self::SUSPENDED_SHARE));

        foreach ($context->random->sample($candidates, $count) as $member) {
            $this->userModeration->suspend($context->superAdmin(), $member);
            $this->audit->record($context->superAdmin(), 'admin.users.suspend', $member);
        }
    }
}
