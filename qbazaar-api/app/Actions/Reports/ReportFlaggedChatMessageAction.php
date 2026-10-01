<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Events\Reports\ReportCreated;
use App\Models\Message;
use App\Models\Report;
use App\Services\Moderation\ModerationRulesService;

/**
 * Screens a delivered chat message against the ad moderation rules and
 * files a system report for staff when it matches.
 *
 * The message itself is never blocked or altered: a rule hit is a signal
 * for a moderator, not proof of abuse. Phone numbers are not screened
 * because exchanging them is a normal step of a private negotiation.
 */
class ReportFlaggedChatMessageAction
{
    public function __construct(private readonly ModerationRulesService $rules) {}

    public function execute(Message $message): ?Report
    {
        $bannedWords = $this->rules->containsBannedWords($message->body);
        $links = $this->rules->containsExternalLink($message->body);

        if ($bannedWords === [] && $links === []) {
            return null;
        }

        $alreadyReported = Report::query()
            ->whereNull('reporter_id')
            ->where('target_type', ReportTarget::MESSAGE->value)
            ->where('target_id', $message->id)
            ->exists();

        if ($alreadyReported) {
            return null;
        }

        /** @var Report $report */
        $report = Report::query()->create([
            'reporter_id' => null,
            'target_type' => ReportTarget::MESSAGE->value,
            'target_id' => $message->id,
            'category' => ($bannedWords !== [] ? ReportCategory::FRAUD : ReportCategory::SPAM)->value,
            'description' => $this->describeMatches($bannedWords, $links),
            'status' => ReportStatus::PENDING->value,
        ]);

        ReportCreated::dispatch($report);

        return $report;
    }

    /**
     * @param list<string> $bannedWords
     * @param list<string> $links
     */
    private function describeMatches(array $bannedWords, array $links): string
    {
        $matches = [];

        if ($bannedWords !== []) {
            $matches[] = 'banned words: ' . implode(', ', $bannedWords);
        }

        if ($links !== []) {
            $matches[] = 'external links: ' . implode(', ', $links);
        }

        return 'Auto-moderation matched ' . implode('; ', $matches);
    }
}
