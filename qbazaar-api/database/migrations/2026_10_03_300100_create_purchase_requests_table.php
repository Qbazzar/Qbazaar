<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Buy Now" requests made inside a conversation.
 *
 *  - `is_open` is TRUE while the request is pending and NULL otherwise, so
 *    the unique (ad_id, buyer_id, is_open) index allows one open request per
 *    buyer and ad even when two requests race. Its ad_id prefix also serves
 *    "close every open request on this ad".
 *  - Like offers, only the conversation FK cascades: MySQL refuses a second
 *    cascade path to the same table, and deleting the conversation already
 *    removes its requests.
 *  - `order_id` is set on acceptance; the order outlives the request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('ad_id')->constrained('ads')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->char('currency', 3)->default('QAR');
            $table->string('note', 280)->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_open')->nullable();
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['ad_id', 'buyer_id', 'is_open'], 'purchase_requests_one_open_unique');
            $table->index(['conversation_id', 'created_at'], 'purchase_requests_conversation_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
