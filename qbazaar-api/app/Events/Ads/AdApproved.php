<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an admin manually approves a PENDING ad from the admin panel.
 * Kept as a separate event from {@see AdPublished} so notifications and
 * activity logs can tell "auto-approved" from "manually approved by reviewer".
 */
class AdApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Ad $ad) {}
}
