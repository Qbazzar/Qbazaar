<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

final readonly class RefreshOptions
{
    public const int DEFAULT_DAYS = 60;

    /**
     * @param int $days how long refreshed demo ads stay live from now
     * @param bool $reviveExpired bring expired demo ads back to live
     * @param bool $curateHome fill the home "Best selling" rail and featured companies, when staff have not
     */
    public function __construct(
        public bool $dryRun = false,
        public int $days = self::DEFAULT_DAYS,
        public bool $reviveExpired = true,
        public bool $curateHome = false,
    ) {}
}
