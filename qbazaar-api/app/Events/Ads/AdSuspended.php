<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Models\Ad;
use App\Models\ModerationSanction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff suspended or removed an ad; `sanction` says which and why. Fires only
 * once the change is committed.
 */
class AdSuspended implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ad $ad,
        public readonly ModerationSanction $sanction,
    ) {}
}
