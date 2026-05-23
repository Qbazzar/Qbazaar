<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QBazaar `users` table.
     *
     * Key shape decisions:
     *  - ULID primary key (URL-safe, monotonic, no enumeration leak).
     *  - `phone` is mandatory + unique because Qatari classifieds rely on
     *    phone-first auth; format is enforced at the application layer.
     *  - We use boolean `email_verified` / `phone_verified` flags
     *    instead of Laravel's default `email_verified_at` because we treat
     *    verification as a binary, time-stamped via activity log.
     *  - Composite index on `(status, deleted_at)` speeds up the most common
     *    admin/moderation filters ("show me all active, non-deleted sellers").
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');

            $table->string('account_type')->default('private');
            $table->string('status')->default('active');

            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);

            $table->string('language', 2)->default('ar');
            $table->string('avatar_url')->nullable();

            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
