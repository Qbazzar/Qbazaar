<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each scheduled prune deletes by a single age column. Without these indexes
 * every run would scan the whole table it prunes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table): void {
            $table->index('expires_at', 'refresh_tokens_expires_at_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index('read_at', 'notifications_read_at_idx');
        });

        // Also serves the admin activity page, which lists newest first.
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index('created_at', 'activity_log_created_at_idx');
        });

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->index('failed_at', 'failed_jobs_failed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table): void {
            $table->dropIndex('refresh_tokens_expires_at_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_read_at_idx');
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('activity_log_created_at_idx');
        });

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->dropIndex('failed_jobs_failed_at_idx');
        });
    }
};
