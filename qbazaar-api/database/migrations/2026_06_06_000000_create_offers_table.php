<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offers — buyer-initiated price proposals that hang off a chat thread.
     *
     *  - ULID PK so channel names + URLs stay non-enumerable.
     *  - `ad_id` / `buyer_id` / `seller_id` are denormalised from the parent
     *    conversation. Three reasons:
     *      (a) the "one active offer per (buyer, ad)" invariant resolves via
     *          a single index lookup instead of a join through conversations,
     *      (b) the seller's "offers on my ads" digest stays cheap, and
     *      (c) if Sprint 11 ever reassigns an ad to a new owner, the
     *          historical offer row stays anchored to the ORIGINAL seller —
     *          same rationale as snapshotting seller_id on conversations.
     *  - `message_id` is the chat bubble we create alongside the offer so the
     *    transcript renders the "I offer X" line inline. `ON DELETE SET NULL`
     *    keeps the offer auditable even if moderation wipes the message.
     *  - Three indexes by access pattern:
     *      1. (buyer_id, ad_id, status) — active-offer uniqueness guard +
     *         "do I already have an open offer on this ad?" lookups.
     *      2. (conversation_id, created_at DESC) — list endpoint scrolls
     *         the thread's offer history newest-first.
     *      3. (status, expires_at) — ExpireOldOffersJob range-scans pending
     *         rows whose expires_at < now() without touching ad/buyer
     *         columns.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            // No cascade here — the conversation_id cascade already removes
            // offers when an Ad is deleted (Ad → Conversation → Offer).
            // Declaring a second cascading path on (offers.ad_id → ads.id)
            // would breach MySQL's "no multiple cascade paths to the same
            // table" restriction.
            $table->foreignUlid('ad_id')
                ->constrained('ads')
                ->restrictOnDelete();

            // Neither user FK cascades — the conversation_id FK already
            // covers user deletion (User → Conversation → Offer via
            // conversation cascades). MySQL forbids multiple cascading
            // paths to the same table, so we restrict here and trust the
            // conversation cascade to do the work.
            $table->foreignUlid('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUlid('seller_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Nullable because we use `nullOnDelete` — Spatie / moderation
            // workflows can wipe the linked message without nuking the
            // offer's audit trail.
            $table->foreignUlid('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('QAR');
            $table->string('note', 280)->nullable();

            $table->enum('status', [
                'pending', 'accepted', 'rejected', 'withdrawn', 'expired',
            ])->default('pending');

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            // Active-offer guard + "my open offers on this ad" lookup.
            $table->index(
                ['buyer_id', 'ad_id', 'status'],
                'offers_buyer_ad_status_idx',
            );

            // Conversation transcript — list offers newest first.
            $table->index(
                ['conversation_id', 'created_at'],
                'offers_conversation_created_idx',
            );

            // Expiry job — `status = pending AND expires_at < now()`.
            $table->index(
                ['status', 'expires_at'],
                'offers_status_expires_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
