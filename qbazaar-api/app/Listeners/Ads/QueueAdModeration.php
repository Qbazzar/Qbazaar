<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Events\Ads\AdSubmittedForReview;
use App\Jobs\Ads\ModerateAdJob;

class QueueAdModeration
{
    public function handle(AdSubmittedForReview $event): void
    {
        ModerateAdJob::dispatch($event->ad->id);
    }
}
