<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller reviews — a buyer rates a seller after a deal (gated on an accepted
 * offer for the ad). One review per (reviewer, ad). The seller's running
 * average + count are denormalised onto the users table for cheap profile
 * reads and recomputed whenever a review changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('ad_id')
                ->constrained('ads')
                ->cascadeOnDelete();

            $table->foreignUlid('seller_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUlid('reviewer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            $table->timestamps();

            // One review per buyer per ad.
            $table->unique(['reviewer_id', 'ad_id']);
            // Profile listing + average recompute scan by seller.
            $table->index(['seller_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });
        Schema::dropIfExists('reviews');
    }
};
