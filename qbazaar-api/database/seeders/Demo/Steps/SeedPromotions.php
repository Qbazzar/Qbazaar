<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Promotions\PurchasePromotionAction;
use App\Actions\Promotions\ReviewPromotionTransferAction;
use App\Enums\AdStatus;
use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Services\Promotions\PromotionCatalog;
use App\Services\Promotions\PromotionLifecycleService;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoFinance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Paid promotions bought the two ways sellers can pay: from the wallet,
 * which starts at once, or by bank transfer that an admin confirms or
 * rejects. One expired promotion was bought a full term ago and ended by
 * the same expiry the scheduler runs.
 */
final class SeedPromotions
{
    private const array PLAN = [
        [PromotionStatus::ACTIVE, PromotionPaymentMethod::WALLET],
        [PromotionStatus::PENDING_PAYMENT, PromotionPaymentMethod::BANK_TRANSFER],
        [PromotionStatus::EXPIRED, PromotionPaymentMethod::WALLET],
        [PromotionStatus::ACTIVE, PromotionPaymentMethod::BANK_TRANSFER],
        [PromotionStatus::REJECTED, PromotionPaymentMethod::BANK_TRANSFER],
    ];

    private const float PROMOTED_SHARE = 0.05;

    private const string TRANSFER_REJECTION = 'No transfer with this reference reached our account.';

    public function __construct(
        private readonly PurchasePromotionAction $purchase,
        private readonly ReviewPromotionTransferAction $reviewTransfer,
        private readonly PromotionLifecycleService $lifecycle,
        private readonly PromotionCatalog $catalog,
        private readonly DemoFinance $finance,
    ) {}

    public function run(DemoContext $context): void
    {
        $count = max(count(self::PLAN), (int) round($context->options->ads * self::PROMOTED_SHARE));
        $ads = $this->candidateAds($count);

        foreach ($ads as $index => $ad) {
            [$status, $method] = self::PLAN[$index % count(self::PLAN)];
            $type = $status === PromotionStatus::EXPIRED ? PromotionType::PUSH_UP : $context->random->pick(PromotionType::cases());

            $this->promote($context, $ad, $type, $status, $method);
        }
    }

    private function promote(DemoContext $context, Ad $ad, PromotionType $type, PromotionStatus $status, PromotionPaymentMethod $method): void
    {
        $offer = $this->catalog->offerFor($type);
        $boughtAt = $status === PromotionStatus::EXPIRED
            ? $context->clock->daysAgo($offer->durationDays + 1)
            : $context->clock->daysAgo(0, $context->random->int(120, 2_000));

        if ($method === PromotionPaymentMethod::WALLET) {
            $this->finance->ensureWithdrawable($context, $ad->user, $offer->price, $boughtAt->copy()->subHour());
        }

        $reference = $method === PromotionPaymentMethod::BANK_TRANSFER ? 'PRM-' . strtoupper(Str::random(8)) : null;
        $promotion = $context->clock->at($boughtAt, fn (): AdPromotion => ($this->purchase)($ad->user, $ad->id, $type, $method, $reference));

        $this->settle($context, $promotion, $status, $method, $boughtAt->copy()->addHours(2));
    }

    private function settle(DemoContext $context, AdPromotion $promotion, PromotionStatus $status, PromotionPaymentMethod $method, Carbon $reviewedAt): void
    {
        $admin = $context->superAdmin();

        match (true) {
            $status === PromotionStatus::EXPIRED => $this->lifecycle->expireIfDue($promotion),
            $status === PromotionStatus::REJECTED => $context->clock->at($reviewedAt, fn () => $this->reviewTransfer->reject($promotion, $admin, self::TRANSFER_REJECTION)),
            $status === PromotionStatus::ACTIVE && $method === PromotionPaymentMethod::BANK_TRANSFER => $context->clock->at($reviewedAt, fn () => $this->reviewTransfer->confirm($promotion, $admin)),
            default => null,
        };
    }

    /**
     * Live listings nobody has reserved, oldest first so the expired
     * promotion sits on a listing that was already up a term ago.
     *
     * @return list<Ad>
     */
    private function candidateAds(int $count): array
    {
        $ads = Ad::query()
            ->with('user')
            ->where('status', AdStatus::ACTIVE->value)
            ->whereNull('reserved_at')
            ->whereNotIn('id', AdPromotion::query()->select('ad_id'))
            ->orderBy('published_at')
            ->limit($count)
            ->get()
            ->all();

        $oldest = array_shift($ads);

        if ($oldest === null) {
            return [];
        }

        $expiredSlot = array_search([PromotionStatus::EXPIRED, PromotionPaymentMethod::WALLET], self::PLAN, true);
        array_splice($ads, (int) $expiredSlot, 0, [$oldest]);

        return $ads;
    }
}
