<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recently-viewed ads — append-only view log.
     *
     *  - Both authenticated and anonymous viewers are tracked. `user_id` is
     *    nullable; anon clients pass a stable `X-Session-Id` header that
     *    lands in `session_id`. Exactly one of the two is set per row.
     *  - No unique constraint on (user_id, ad_id): we keep multiple rows
     *    for the same pair so the inline cap-50 cleanup can evict by
     *    `viewed_at` order. The hourly throttle (Cache::lock) is what
     *    prevents row spam, not the schema.
     *  - Three composite indexes match the read paths:
     *      1. `(user_id, viewed_at desc)` — "my recently viewed".
     *      2. `(session_id, viewed_at desc)` — anon dedup / recall.
     *      3. `(ad_id, viewed_at desc)` — future analytics aggregator
     *         (per-ad view-count over time) without scanning the table.
     *  - Counts on `ads.views_count` are still incremented separately so
     *    feed cards can render the headline number without an extra join.
     */
    public function up(): void
    {
        Schema::create('recently_viewed', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('session_id', 64)->nullable();

            $table->foreignUlid('ad_id')
                ->constrained('ads')
                ->cascadeOnDelete();

            $table->timestamp('viewed_at');

            $table->index(['user_id', 'viewed_at'], 'recently_viewed_user_viewed_idx');
            $table->index(['session_id', 'viewed_at'], 'recently_viewed_session_viewed_idx');
            $table->index(['ad_id', 'viewed_at'], 'recently_viewed_ad_viewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed');
    }
};
