<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codes now go to an email address as well as a phone, so the column
     * holds whichever recipient the purpose targets.
     */
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['phone', 'purpose', 'used_at']);
        });

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->renameColumn('phone', 'recipient');
        });

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->index(['recipient', 'purpose', 'used_at']);
            $table->index(['recipient', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['recipient', 'purpose', 'created_at']);
            $table->dropIndex(['recipient', 'purpose', 'used_at']);
        });

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->renameColumn('recipient', 'phone');
        });

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->index(['phone', 'purpose', 'used_at']);
        });
    }
};
