<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Events\Ads\AdApproved;
use App\Events\Ads\AdPublished;

/**
 * Pushes an ad into Meilisearch when it becomes publicly visible.
 *
 * Why a listener (instead of an inline call inside `Ad::publish()`)?
 *  - Same indexing behaviour applies to admin-approved ads (which arrive via
 *    AdApproved instead of AdPublished). One listener handles both pathways.
 *  - Keeps the publish flow declarative — search side effects are pluggable.
 *
 * Note: `Ad::publish()` already calls `$ad->searchable()` for the synchronous
 * happy-path; this listener is the safety net for the manual-approval path
 * AND will be the single hook when we move indexing to the queue in Wave C.
 */
class IndexAdInSearch
{
    public function handle(AdPublished|AdApproved $event): void
    {
        $event->ad->syncSearchIndex();
    }
}
