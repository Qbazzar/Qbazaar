<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ads — the core marketplace listing.
     *
     *  - ULID primary so URLs / API IDs are non-enumerable, sortable, and
     *    portable across services without an integer<->ULID shim.
     *  - `custom_fields` carries category-specific attributes (mileage, room
     *    count, …) as JSON; the schema for each category lives on the
     *    category row, so adding new fields needs no migration.
     *  - Counters (`views_count`, `favorites_count`) are denormalised — we
     *    increment them via observers / events, never join-then-count at read
     *    time.
     *  - Indexes are tuned for the four major read paths:
     *      1. Public feed (status + published_at desc)
     *      2. Seller dashboard ("my ads" — all statuses, recent first)
     *      3. Browse-by-category (category + status + published_at desc)
     *      4. Browse-by-location (location + status + published_at desc)
     */
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUlid('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->foreignUlid('location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table->string('title', 120);
            $table->text('description');

            // NULL when price_type is `free` or `contact`. The CreateAdRequest
            // / UpdateAdRequest force-nulls the value before persisting.
            $table->decimal('price', 12, 2)->nullable();

            $table->enum('price_type', ['fixed', 'negotiable', 'free', 'contact'])
                ->default('fixed');

            $table->char('currency', 3)->default('QAR');

            $table->enum('condition', ['new', 'like_new', 'used'])->nullable();

            // Lifecycle — DRAFT is the start state. Wave A skips the pending
            // step (no auto-moderation yet), so `publish()` transitions
            // straight to ACTIVE.
            $table->enum('status', [
                'draft', 'pending', 'active', 'sold', 'expired', 'rejected', 'blocked',
            ])->default('draft');

            $table->json('custom_fields')->nullable();

            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('favorites_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // 1. Public feed — latest active ads.
            $table->index(['status', 'published_at'], 'ads_status_published_idx');

            // 2. Seller dashboard — caller's ads grouped by status, recent first.
            $table->index(['user_id', 'status', 'created_at'], 'ads_user_status_created_idx');

            // 3. Browse a category.
            $table->index(['category_id', 'status', 'published_at'], 'ads_category_status_published_idx');

            // 4. Browse a location.
            $table->index(['location_id', 'status', 'published_at'], 'ads_location_status_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
