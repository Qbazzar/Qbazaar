<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages — individual lines inside a conversation.
     *
     *  - `type` enum carries text|offer|system. Wave A only emits `text`;
     *    `offer` is reserved for Sprint 9 (offer cards inline with chat),
     *    `system` for moderation notices / read receipts that are visible
     *    in the transcript.
     *  - `read_at` is per-message, not per-conversation, so we can render
     *    per-bubble read receipts in Sprint 9 without a follow-up migration.
     *  - Indexes:
     *      1. (conversation_id, created_at) — cursor pagination, newest first.
     *      2. (conversation_id, sender_id, read_at) — unread-count query
     *         "messages in this convo NOT sent by me where read_at IS NULL".
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignUlid('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('body');

            $table->enum('type', ['text', 'offer', 'system'])->default('text');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // 1. Cursor pagination — `where conversation_id = ? order by created_at desc`.
            $table->index(['conversation_id', 'created_at'], 'messages_conv_created_idx');

            // 2. Unread count — `where conversation_id = ? and sender_id != ? and read_at is null`.
            $table->index(['conversation_id', 'sender_id', 'read_at'], 'messages_conv_sender_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
