<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a nullable JSON column for per-user privacy toggles.
     *
     * Nullable + app-level defaults (see App\Data\Account\PrivacySettings)
     * means existing rows don't need a backfill — the User model casts NULL
     * to the default DTO on read.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('privacy_settings')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('privacy_settings');
        });
    }
};
