<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's standard `notifications` table, adapted for ULID notifiables.
     *
     * Shape mirrors `php artisan make:notifications-table` with two important
     * tweaks:
     *  - `id` stays UUID (Laravel's Notifiable trait generates UUID v4 per
     *    record — keeping that means the `database` channel works out of the
     *    box without overriding `NotificationSender`).
     *  - The notifiable morph uses `ulidMorphs()` because our User model is
     *    keyed by ULID — the default `morphs()` would create an
     *    `unsigned bigint` which cannot store a 26-char ULID. Same approach
     *    we took for `media.model_id` and `personal_access_tokens.tokenable_id`.
     *
     * Indexes:
     *  - `(notifiable_type, notifiable_id)` is added by `ulidMorphs()` —
     *    powers every "list this user's notifications" query.
     *  - `(notifiable_type, notifiable_id, read_at)` is added explicitly so
     *    the unread-count + unread filter use an index-only scan.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->ulidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_notifiable_read_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
