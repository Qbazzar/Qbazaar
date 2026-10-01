<?php

declare(strict_types=1);

namespace App\Listeners\Users;

use App\Events\Users\UserStatusChanged;
use App\Services\Catalog\CatalogCache;
use App\Services\Users\SellerListingsVisibilityService;

class SyncSellerListingsVisibility
{
    public function __construct(
        private readonly SellerListingsVisibilityService $listings,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function handle(UserStatusChanged $event): void
    {
        if ($event->becameInactive()) {
            $this->listings->hide($event->user);
        } elseif ($event->becameActive()) {
            $this->listings->restore($event->user);
        } else {
            return;
        }

        $this->catalogCache->featuredAdsChanged();
    }
}
