<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refresh tokens — long-lived companions to Sanctum access tokens.
     *
     * Why a separate table instead of more Sanctum tokens?
     *  - Different lifetime (~30 days vs ~15 min) makes them logically distinct.
     *  - We HASH the raw value so a leaked DB row can't be replayed.
     *  - We rotate on every use; `used_at` lets us detect replays of an old
     *    token and burn the whole family on suspicion of theft.
     *  - `device_fingerprint` lets us later show "active sessions" and
     *    revoke per-device without touching access tokens.
     */
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('device_fingerprint')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
