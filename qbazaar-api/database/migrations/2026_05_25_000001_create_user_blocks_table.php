<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot table tracking which user has blocked which.
     *
     *  - Composite primary key (blocker_id, blocked_id) so duplicate POSTs
     *    no-op via firstOrCreate without raising integrity errors.
     *  - Cascade on delete: when either user is hard-deleted, their block
     *    rows are cleaned up automatically.
     *  - Indexed in both directions: ad-listing queries filter by
     *    blocker_id = me; messaging permissions filter by blocked_id = me.
     */
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table): void {
            $table->foreignUlid('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
