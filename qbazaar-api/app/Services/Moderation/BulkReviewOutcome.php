<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * How a bulk review went: the ads it moved and those it left because they
 * were no longer waiting (handled by someone else, deleted, or never pending).
 */
final readonly class BulkReviewOutcome
{
    public function __construct(
        public int $done,
        public int $skipped,
    ) {}
}
