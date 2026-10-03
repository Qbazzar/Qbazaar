<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\CategoryCommissionRate;
use App\Models\User;
use App\Services\Orders\CommissionRates;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Sets or clears a category's own commission percent. Orders already
 * placed keep the rate frozen on them; only new orders see the change.
 */
class SetCategoryCommissionRateAction
{
    public function __construct(
        private readonly CommissionRates $rates,
    ) {}

    public function set(string $categoryId, string $rate, User $actor): void
    {
        $rate = Money::of($rate);

        DB::transaction(function () use ($categoryId, $rate, $actor): void {
            $old = $this->currentRate($categoryId);

            CategoryCommissionRate::query()->updateOrCreate(
                ['category_id' => $categoryId],
                ['rate' => $rate, 'updated_by' => $actor->id],
            );

            $this->log($actor, 'updated', $categoryId, $old, $rate);
            $this->rates->forget();
        });
    }

    public function clear(string $categoryId, User $actor): void
    {
        DB::transaction(function () use ($categoryId, $actor): void {
            $old = $this->currentRate($categoryId);

            if ($old === null) {
                return;
            }

            CategoryCommissionRate::query()->whereKey($categoryId)->delete();

            $this->log($actor, 'deleted', $categoryId, $old, null);
            $this->rates->forget();
        });
    }

    private function currentRate(string $categoryId): ?string
    {
        return CategoryCommissionRate::query()->find($categoryId)?->rate;
    }

    private function log(User $actor, string $event, string $categoryId, ?string $old, ?string $new): void
    {
        activity('settings')
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['key' => 'category_commission_rate', 'category_id' => $categoryId, 'old' => $old, 'new' => $new])
            ->log('Category commission rate ' . $event);
    }
}
