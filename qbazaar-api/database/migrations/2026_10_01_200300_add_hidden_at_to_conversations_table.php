<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each side hides a conversation for itself only; the next message
     * clears both stamps. The inbox indexes gain the stamp so "my visible
     * conversations, newest first" stays an index range per side.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('buyer_hidden_at')->nullable()->after('last_message_params');
            $table->timestamp('seller_hidden_at')->nullable()->after('buyer_hidden_at');

            $table->index(['buyer_id', 'buyer_hidden_at', 'last_message_at'], 'conversations_buyer_inbox_idx');
            $table->index(['seller_id', 'seller_hidden_at', 'last_message_at'], 'conversations_seller_inbox_idx');
        });

        // The new indexes lead with the same columns, so the foreign keys keep an index.
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_buyer_last_msg_idx');
            $table->dropIndex('conversations_seller_last_msg_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->index(['buyer_id', 'last_message_at'], 'conversations_buyer_last_msg_idx');
            $table->index(['seller_id', 'last_message_at'], 'conversations_seller_last_msg_idx');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_buyer_inbox_idx');
            $table->dropIndex('conversations_seller_inbox_idx');
            $table->dropColumn(['buyer_hidden_at', 'seller_hidden_at']);
        });
    }
};
