<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('purpose', 32)
                ->default(OtpPurpose::PHONE_VERIFICATION->value)
                ->after('phone');

            $table->dropIndex(['phone', 'used_at']);
            $table->index(['phone', 'purpose', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['phone', 'purpose', 'used_at']);
            $table->index(['phone', 'used_at']);
            $table->dropColumn('purpose');
        });
    }
};
