<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User-submitted abuse reports against ads / users / conversations /
     * messages.
     *
     *  - ULID PK — keeps URLs non-enumerable.
     *  - `target_type` is a polymorphic discriminator. We deliberately do NOT
     *    declare a foreign key for `target_id` because the target table
     *    varies; the controller performs an explicit existence lookup so the
     *    REPORT_INVALID_TARGET branch maps to a 422 instead of a generic DB
     *    error.
     *  - `reporter_id` cascades on user deletion (per GDPR delete-my-account
     *    flow). `reviewed_by` does NOT cascade because admin records should
     *    not delete the historical audit trail — set null instead.
     *  - Three indexes serve the three known access patterns:
     *      1. (target_type, target_id, status) — "is this target currently
     *         reported?" check used by the duplicate guard and the future
     *         Filament moderation queue.
     *      2. (reporter_id, created_at) — list / rate-limit "what have I
     *         reported recently?".
     *      3. (status, created_at) — admin queue scrolls newest-pending.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('target_type', ['ad', 'user', 'conversation', 'message']);
            $table->ulid('target_id');

            $table->enum('category', [
                'spam',
                'fraud',
                'inappropriate',
                'offensive',
                'duplicate',
                'wrong_category',
                'other',
            ]);

            $table->text('description')->nullable();

            $table->enum('status', ['pending', 'reviewed', 'dismissed', 'actioned'])
                ->default('pending');

            $table->foreignUlid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(
                ['target_type', 'target_id', 'status'],
                'reports_target_status_idx',
            );

            $table->index(
                ['reporter_id', 'created_at'],
                'reports_reporter_created_idx',
            );

            $table->index(
                ['status', 'created_at'],
                'reports_status_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
