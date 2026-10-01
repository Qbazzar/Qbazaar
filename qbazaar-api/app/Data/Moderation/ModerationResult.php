<?php

declare(strict_types=1);

namespace App\Data\Moderation;

use App\Actions\Ads\ModerateAdAction;
use Spatie\LaravelData\Data;

/**
 * Outcome of running an ad through {@see ModerateAdAction}.
 *
 * Carries:
 *  - `clean`   — true when no rule fired. The caller can publish straight to ACTIVE.
 *  - `flags`   — distinct rule identifiers that fired (`banned_words`, `phone`,
 *                `external_link`, and `duplicate_image` once the queued
 *                image check has run).
 *  - `details` — per-flag specifics (matched words, URLs). Logged into the
 *                activity-log row for later review; not surfaced verbatim to
 *                the seller (we paraphrase instead).
 *
 * Kept immutable + serialisable so it can ride inside the AdRejected event
 * payload without leaking the original Ad model.
 */
class ModerationResult extends Data
{
    public const DUPLICATE_IMAGE = 'duplicate_image';

    /**
     * @param list<string> $flags
     * @param array<string, mixed> $details
     */
    public function __construct(
        public bool $clean,
        public array $flags,
        public array $details,
    ) {}

    public static function clean(): self
    {
        return new self(true, [], []);
    }

    /**
     * @param list<string> $flags
     * @param array<string, mixed> $details
     */
    public static function rejected(array $flags, array $details): self
    {
        return new self(false, $flags, $details);
    }

    /**
     * Replace the duplicate-image finding, keeping every other flag, so the
     * check can run again without stacking stale matches.
     *
     * @param list<string> $duplicateAdIds
     */
    public function withDuplicateImages(array $duplicateAdIds): self
    {
        $flags = array_values(array_diff($this->flags, [self::DUPLICATE_IMAGE]));
        $details = array_diff_key($this->details, [self::DUPLICATE_IMAGE => true]);

        if ($duplicateAdIds !== []) {
            $flags[] = self::DUPLICATE_IMAGE;
            $details[self::DUPLICATE_IMAGE] = ['duplicate_ad_ids' => $duplicateAdIds];
        }

        return new self($flags === [], $flags, $details);
    }
}
