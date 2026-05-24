<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved searches — per-user named search snapshots.
     *
     *  - ULID primary so external references (e.g. push notifications linking
     *    back to "new matches for search X") never expose enumerable IDs.
     *  - `query_params` is the raw filter envelope as submitted to /search,
     *    so re-running the saved search is a single object replay on the
     *    client. We don't normalise — search params evolve sprint by sprint
     *    and the JSON column absorbs that change without migrations.
     *  - `(user_id, created_at desc)` index supports the "list my saved
     *    searches, newest first" read path used by the account screen.
     *
     * Limit (max 10 / user) is enforced in the controller, not at the
     * schema level, so the rule can change without a migration.
     */
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name', 60);
            $table->json('query_params');

            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'saved_searches_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
