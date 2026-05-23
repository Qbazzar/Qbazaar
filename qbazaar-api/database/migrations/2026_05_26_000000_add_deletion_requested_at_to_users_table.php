<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `deletion_requested_at` so we can record when the user asked us
     * to wipe their account. The 30-day grace period (config:
     * `qbazaar.account.deletion_grace_period_days`) starts ticking from this
     * timestamp; `DeleteAccountJob` reads it to decide whether the request is
     * still active when it finally fires.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('deletion_requested_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('deletion_requested_at');
        });
    }
};
