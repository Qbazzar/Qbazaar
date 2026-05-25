<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ModerationRuleLanguage;
use App\Enums\ModerationRuleType;
use App\Models\ModerationRule;
use Illuminate\Database\Seeder;

/**
 * Seeds the moderation_rules table from the legacy config/moderation.php list.
 *
 * Idempotent: a re-run skips rows whose (type, value, language) already exist
 * thanks to the unique constraint. We use `firstOrCreate` rather than upsert so
 * existing rule edits made by humans in production aren't trampled by a deploy
 * that re-seeds.
 *
 * Language scope is detected from the value itself — Arabic words ship with
 * `ar`, everything else with `en`. This keeps the seeded data faithful to the
 * original list while letting moderators expand into the third `any` slot
 * later via the admin UI.
 */
class ModerationRulesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<string> $bannedWords */
        $bannedWords = (array) config('moderation.banned_words', []);

        foreach ($bannedWords as $word) {
            if (! is_string($word) || trim($word) === '') {
                continue;
            }

            $language = $this->detectLanguage($word);

            ModerationRule::query()->firstOrCreate(
                [
                    'type' => ModerationRuleType::BANNED_WORD->value,
                    'value' => $word,
                    'language' => $language->value,
                ],
                [
                    'is_active' => true,
                ],
            );
        }

        // Reset cached lookups so the next moderation call sees the seeded rows.
        ModerationRule::flushCache();
    }

    /**
     * Detect Arabic vs Latin via a single Unicode property scan. Falls back to
     * `any` only when the string contains neither alphabet (rare; usually
     * single-symbol entries that we want enforced globally anyway).
     */
    private function detectLanguage(string $value): ModerationRuleLanguage
    {
        if (preg_match('/\p{Arabic}/u', $value) === 1) {
            return ModerationRuleLanguage::AR;
        }

        if (preg_match('/[A-Za-z]/', $value) === 1) {
            return ModerationRuleLanguage::EN;
        }

        return ModerationRuleLanguage::ANY;
    }
}
