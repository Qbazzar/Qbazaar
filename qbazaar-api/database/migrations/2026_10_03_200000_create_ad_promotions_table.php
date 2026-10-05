<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paid promotions. The price and duration are frozen when the promotion
     * is bought, so a later price change never touches it.
     *
     *  - `open_slot` is "{ad_id}:{type}" while the promotion is pending or
     *    active and NULL otherwise; its unique index allows one open
     *    promotion of each type per ad even when two requests race.
     *  - The ad and the user are nullable with SET NULL: a paid promotion is
     *    a financial record and must survive an erased account.
     *  - `ads.promotion_rank` caches the highest rank among the ad's active
     *    promotions, so search and the home feed read it without a join.
     */
    public function up(): void
    {
        Schema::create('ad_promotions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ad_id')->nullable()->constrained('ads')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->string('open_slot', 48)->nullable()->unique();
            $table->string('payment_method', 20);
            $table->decimal('price', 12, 2);
            $table->char('currency', 3)->default('QAR');
            $table->unsignedSmallInteger('duration_days');
            $table->string('transfer_reference', 64)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            // Expiry sweep: active promotions whose end has passed.
            $table->index(['status', 'ends_at'], 'ad_promotions_status_ends_idx');
            // Admin queue of transfers waiting for confirmation, oldest first.
            $table->index(['status', 'created_at'], 'ad_promotions_status_created_idx');
            // The seller's own promotions, newest first.
            $table->index(['user_id', 'created_at'], 'ad_promotions_user_created_idx');
            // An ad's active promotions when its rank is recomputed.
            $table->index(['ad_id', 'status'], 'ad_promotions_ad_status_idx');
        });

        Schema::table('ads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('promotion_rank')->default(0);
            // Promoted ads leading the home feed.
            $table->index(['status', 'seller_active', 'promotion_rank'], 'ads_status_seller_promotion_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_status_seller_promotion_idx');
            $table->dropColumn('promotion_rank');
        });

        Schema::dropIfExists('ad_promotions');
    }
};
