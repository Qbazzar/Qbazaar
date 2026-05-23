<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `ip_address` and `device_label` to Sanctum's personal_access_tokens
     * so the GET /account/sessions endpoint can render a meaningful "what is
     * this session?" line per row.
     *
     * Both columns are nullable — pre-Sprint-2 tokens (and the
     * non-login-flow tokens issued by `php artisan` etc.) simply leave them
     * empty.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('last_used_at');
            $table->string('device_label')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn(['ip_address', 'device_label']);
        });
    }
};
