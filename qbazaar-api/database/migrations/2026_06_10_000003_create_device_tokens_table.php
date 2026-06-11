<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Device tokens — FCM registration tokens for web push (and later mobile).
     *
     * Why a dedicated table?
     *  - One row per browser/device: a user reading QBazaar on a laptop and a
     *    phone holds two rows, and we fan a push out to every row.
     *  - `token` is unique GLOBALLY (not per user) because an FCM token
     *    identifies the device, and a device belongs to whoever logged in
     *    last — re-registering simply re-points the row at the new user.
     *  - `last_used_at` lets a scheduled prune drop tokens that stopped
     *    refreshing; FCM tokens go stale silently and Google recommends
     *    expiring inactive ones.
     *  - 512 chars is generous headroom (real tokens are ~150-200 ASCII chars)
     *    and still safely indexable under InnoDB's 3072-byte key limit
     *    (512 × 4 bytes utf8mb4 = 2048).
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 16)->default('web'); // web|android|ios
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
