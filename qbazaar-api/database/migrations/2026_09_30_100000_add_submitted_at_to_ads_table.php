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
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->index(['user_id', 'submitted_at'], 'ads_user_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_user_submitted_idx');
            $table->dropColumn('submitted_at');
        });
    }
};
