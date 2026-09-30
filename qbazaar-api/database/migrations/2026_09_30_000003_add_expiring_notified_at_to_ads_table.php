<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->timestamp('expiring_notified_at')->nullable()->after('expires_at');
            $table->index(['status', 'expires_at'], 'ads_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_status_expires_idx');
            $table->dropColumn('expiring_notified_at');
        });
    }
};
