<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversations — the inbox row tying a buyer to a seller about an ad.
     *
     *  - ULID PK so URLs / channel names stay non-enumerable.
     *  - `seller_id` is snapshotted at start time. Ad ownership rarely
     *    changes today, but Sprint 11 will add admin re-assignment; we
     *    don't want an old conversation to disappear from the seller's
     *    inbox when that happens.
     *  - `last_message_at` + `last_message_preview` are denormalised so the
     *    inbox list never needs a sub-query / join against `messages` —
     *    that's the hot path the UI hits on every refresh.
     *  - Unique (ad_id, buyer_id): exactly one conversation per buyer per
     *    ad. Repeated POSTs to /conversations resolve to the existing row
     *    rather than spawning duplicates.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('ad_id')
                ->constrained('ads')
                ->cascadeOnDelete();

            $table->foreignUlid('buyer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUlid('seller_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 160)->nullable();

            $table->timestamps();

            // One conversation per (ad, buyer) — see find-or-create in
            // StartConversationAction.
            $table->unique(['ad_id', 'buyer_id'], 'conversations_ad_buyer_uq');

            // Inbox ordering for the buyer view — most recent at the top.
            $table->index(['buyer_id', 'last_message_at'], 'conversations_buyer_last_msg_idx');

            // Inbox ordering for the seller view.
            $table->index(['seller_id', 'last_message_at'], 'conversations_seller_last_msg_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
