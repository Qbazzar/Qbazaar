<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per staff member who started setting up two-factor sign-in, read
 * by primary key (the user id) at login and on the admin pages, so the hot
 * users table stays untouched.
 *
 *  - `secret` is the encrypted TOTP key; `confirmed_at` stays NULL until the
 *    first code proves the authenticator app holds it.
 *  - `recovery_codes` holds SHA-256 hashes of the one-time codes.
 *  - `last_used_step` blocks replaying a code inside its 30-second window.
 *  - `device_version` is copied into "trust this browser" cookies; bumping
 *    it revokes every trusted browser at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_two_factor', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->text('secret');
            $table->text('recovery_codes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('last_used_step')->nullable();
            $table->unsignedInteger('device_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_two_factor');
    }
};
