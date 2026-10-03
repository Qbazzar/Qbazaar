<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders. Prices and the commission are frozen when the order is
     * created, so a later price or commission change never touches it.
     *
     *  - `active_ad_id` mirrors `ad_id` while the order is open and is NULL
     *    otherwise; its unique index guarantees one open order per ad even
     *    when two requests race.
     *  - (source, source_id) is unique, so the same accepted offer can never
     *    produce two orders.
     *  - The ad and both users are nullable with SET NULL: the order is a
     *    financial record and must survive an erased account. `ad_title`
     *    keeps what was bought.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ad_id')->nullable()->constrained('ads')->nullOnDelete();
            $table->ulid('active_ad_id')->nullable()->unique();
            $table->foreignUlid('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20);
            $table->ulid('source_id');
            $table->string('status', 20)->default('created');
            $table->string('ad_title', 255);
            $table->char('currency', 3)->default('QAR');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->string('payment_method', 20);
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('awaiting_handover_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_id'], 'orders_source_unique');
            $table->index(['ad_id', 'status'], 'orders_ad_status_idx');
            $table->index(['buyer_id', 'created_at'], 'orders_buyer_created_idx');
            $table->index(['seller_id', 'created_at'], 'orders_seller_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
