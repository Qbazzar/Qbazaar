<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per side of a conversation holds that user's inbox state: the
     * hide stamp, the unread counter and a copy of the conversation's sort
     * keys. The inbox and the unread badge then read one index range of the
     * user's own rows instead of OR-ing the buyer and seller columns and
     * counting messages.
     */
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('conversation_created_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);

            $table->primary(['conversation_id', 'user_id']);
            $table->index(['user_id', 'hidden_at', 'last_message_at', 'conversation_created_at'], 'conversation_participants_inbox_idx');
        });

        $this->backfill('buyer');
        $this->backfill('seller');

        // The inbox indexes about to go are what the buyer_id / seller_id
        // foreign keys use, so each key needs its own index first.
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

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('buyer_hidden_at')->nullable()->after('last_message_params');
            $table->timestamp('seller_hidden_at')->nullable()->after('buyer_hidden_at');

            $table->index(['buyer_id', 'buyer_hidden_at', 'last_message_at'], 'conversations_buyer_inbox_idx');
            $table->index(['seller_id', 'seller_hidden_at', 'last_message_at'], 'conversations_seller_inbox_idx');
        });

        foreach (['buyer', 'seller'] as $side) {
            DB::table('conversations')->update([
                "{$side}_hidden_at" => DB::table('conversation_participants')
                    ->whereColumn('conversation_participants.conversation_id', 'conversations.id')
                    ->whereColumn('conversation_participants.user_id', "conversations.{$side}_id")
                    ->select('hidden_at'),
            ]);
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_buyer_last_msg_idx');
            $table->dropIndex('conversations_seller_last_msg_idx');
        });

        Schema::dropIfExists('conversation_participants');
    }

    /**
     * @param 'buyer'|'seller' $side
     */
    private function backfill(string $side): void
    {
        $unread = DB::table('messages')
            ->selectRaw('COUNT(*)')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->whereColumn('messages.sender_id', '!=', "conversations.{$side}_id")
            ->whereNull('messages.read_at');

        DB::table('conversation_participants')->insertUsing(
            ['conversation_id', 'user_id', 'last_message_at', 'conversation_created_at', 'hidden_at', 'unread_count'],
            DB::table('conversations')
                ->select(['id', "{$side}_id", 'last_message_at', 'created_at', "{$side}_hidden_at"])
                ->selectSub($unread, 'unread_count'),
        );
    }
};
