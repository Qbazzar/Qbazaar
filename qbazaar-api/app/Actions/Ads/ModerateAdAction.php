<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Data\Moderation\ModerationResult;
use App\Models\Ad;
use App\Services\Moderation\DuplicateImageDetector;
use App\Services\Moderation\ModerationRulesService;

/**
 * Runs an ad through the four moderation rule families — banned words,
 * phone-in-text, external links (title + description) and near-duplicate
 * images — and returns a structured outcome. Kept as a thin invokable
 * action because it composes service calls and has no side effects — easy
 * to unit-test and to dispatch from synchronous AND queued contexts.
 *
 * If moderation is disabled (`config('moderation.enabled') === false`) we
 * return a clean result immediately so the publish flow short-circuits to the
 * pre-Wave-B behaviour. This is the single kill-switch the operations team
 * can flip when a regex misfires in production.
 */
class ModerateAdAction
{
    public function __construct(
        private readonly ModerationRulesService $rules,
        private readonly DuplicateImageDetector $duplicateImages,
    ) {}

    public function __invoke(Ad $ad): ModerationResult
    {
        if (! (bool) config('moderation.enabled', true)) {
            return ModerationResult::clean();
        }

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

        $duplicateAdIds = $this->duplicateImages->findDuplicateAdIds($ad);
        if ($duplicateAdIds !== []) {
            $flags[] = 'duplicate_image';
            $details['duplicate_image'] = ['duplicate_ad_ids' => $duplicateAdIds];
        }

        if ($flags === []) {
            return ModerationResult::clean();
        }

        return ModerationResult::rejected($flags, $details);
    }
}
