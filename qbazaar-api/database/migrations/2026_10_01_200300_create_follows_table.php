<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who follows whom. Lists are read newest first by ULID, so each side has a
 * (user, id) index that serves the filter and the cursor order together.
 * The counts live on users and are kept in step by FollowGraph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('followed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['follower_id', 'followed_id'], 'follows_pair_unique');
            $table->index(['followed_id', 'id'], 'follows_followed_idx');
            $table->index(['follower_id', 'id'], 'follows_follower_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('followers_count')->default(0)->after('rating_count');
            $table->unsignedInteger('following_count')->default(0)->after('followers_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['followers_count', 'following_count']);
        });

        Schema::dropIfExists('follows');
    }
};
