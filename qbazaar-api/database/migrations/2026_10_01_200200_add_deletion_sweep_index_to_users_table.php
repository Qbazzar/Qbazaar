<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily deletion sweep filters on `status = pending_deletion AND
     * deletion_requested_at <= cutoff`; without this index it scans users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['status', 'deletion_requested_at'], 'users_status_deletion_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_status_deletion_requested_idx');
        });
    }
};
