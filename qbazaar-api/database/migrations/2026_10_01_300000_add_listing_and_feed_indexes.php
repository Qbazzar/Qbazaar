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
            $table->index(['status', 'price'], 'ads_status_price_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_notifiable_created_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index('last_login_at', 'users_last_login_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_status_price_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_created_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_last_login_at_idx');
        });
    }
};
