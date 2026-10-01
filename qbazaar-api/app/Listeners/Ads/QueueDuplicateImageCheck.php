<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Events\Ads\AdSubmittedForReview;
use App\Jobs\Ads\DetectDuplicateImagesJob;

class QueueDuplicateImageCheck
{
    public function handle(AdSubmittedForReview $event): void
    {
        DetectDuplicateImagesJob::dispatch($event->ad->id);
    }
}
