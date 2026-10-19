<?php

declare(strict_types=1);

namespace App\Services\Admin\Broadcasts;

use App\Enums\BroadcastSegment;

/**
 * A validated broadcast, as the composer form submitted it.
 */
final readonly class BroadcastDraft
{
    public function __construct(
        public BroadcastSegment $segment,
        public ?string $locationId,
        public string $titleAr,
        public string $bodyAr,
        public ?string $titleEn,
        public ?string $bodyEn,
        public bool $sendEmail,
    ) {}
}
