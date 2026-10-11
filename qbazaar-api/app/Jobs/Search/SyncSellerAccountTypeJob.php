<?php

declare(strict_types=1);

namespace App\Jobs\Search;

use App\Enums\QueueName;
use App\Models\User;
use App\Services\Search\AdFacetCounter;
use App\Services\Users\SellerListingsVisibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A seller switched between a private and a business account: copies the new
 * type onto their ads and sends the listed ones to the search index again.
 * Queued because a seller can have thousands of ads, and dispatched after the
 * commit that changed the account so the job reads the new type.
 */
class SyncSellerAccountTypeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 60;

    public function __construct(public readonly string $userId)
    {
        $this->onQueue(QueueName::SEARCH);
        $this->afterCommit();
    }

    public function handle(SellerListingsVisibilityService $listings, AdFacetCounter $facets): void
    {
        $seller = User::query()->find($this->userId);

        if ($seller === null) {
            return;
        }

        $listings->accountTypeChanged($seller);
        $facets->flush();
    }
}
