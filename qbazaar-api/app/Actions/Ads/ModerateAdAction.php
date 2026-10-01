<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Data\Moderation\ModerationResult;
use App\Models\Ad;
use App\Services\Moderation\DuplicateImageDetector;
use App\Services\Moderation\ModerationRulesService;

/**
 * Runs an ad through the auto-moderation checks — banned words, phone in
 * text and external links (title + description), then near-duplicate images
 * — and returns triage hints for the reviewer. It has no side effects and
 * takes no locks; ModerateAdJob calls it on the queue.
 *
 * `moderation.enabled = false` returns a clean result, the kill-switch the
 * operations team can flip when a rule misfires in production.
 */
class ModerateAdAction
{
    public function __construct(
        private readonly ModerationRulesService $rules,
        private readonly DuplicateImageDetector $duplicates,
    ) {}

    public function __invoke(Ad $ad): ModerationResult
    {
        if (! (bool) config('moderation.enabled', true)) {
            return ModerationResult::clean();
        }

        return $this->checkText($ad)->withDuplicateImages($this->duplicates->findDuplicateAdIds($ad));
    }

    private function checkText(Ad $ad): ModerationResult
    {
        $combined = trim($ad->title . "\n" . $ad->description);

        $flags = [];
        $details = [];

        $bannedHits = $this->rules->containsBannedWords($combined);
        if ($bannedHits !== []) {
            $flags[] = 'banned_words';
            $details['banned_words'] = $bannedHits;
        }

        if ($this->rules->containsPhone($combined)) {
            $flags[] = 'phone';
            $details['phone'] = true;
        }

        $linkHits = $this->rules->containsExternalLink($combined);
        if ($linkHits !== []) {
            $flags[] = 'external_link';
            $details['external_link'] = $linkHits;
        }

        return $flags === [] ? ModerationResult::clean() : ModerationResult::rejected($flags, $details);
    }
}
