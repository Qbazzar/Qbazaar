<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Copies business accounts onto their ads' `seller_type` (new ads and every
 * account type change keep it in step on their own). Safe to run any number of
 * times: only ads that still differ are touched, one batch of sellers at a
 * time, so no statement covers the whole table. The deploy runs it after
 * `migrate`.
 */
class SyncAdSellerTypesCommand extends Command
{
    private const int SELLERS_PER_BATCH = 500;

    protected $signature = 'ads:sync-seller-types';

    protected $description = 'Fill ads.seller_type from the seller account type, in batches (idempotent)';

    public function handle(): int
    {
        $updated = 0;

        User::query()
            ->where('account_type', AccountType::BUSINESS->value)
            ->chunkById(self::SELLERS_PER_BATCH, function (Collection $sellers) use (&$updated): void {
                $updated += Ad::withTrashed()
                    ->whereIn('user_id', $sellers->modelKeys())
                    ->where('seller_type', '!=', AccountType::BUSINESS->value)
                    ->toBase()
                    ->update(['seller_type' => AccountType::BUSINESS->value]);
            });

        $this->info("Updated {$updated} ads.");

        return self::SUCCESS;
    }
}
