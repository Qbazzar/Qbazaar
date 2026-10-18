<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The verified-business badge is read wherever a seller is shown (ad pages,
 * chats, profiles, the companies directory), so it lives on users and needs
 * no join. Who granted it is kept beside it for the admin panel; the history
 * (grants, revocations and their reasons) is in the admin audit log.
 *
 * Both columns are nullable additions, which MariaDB applies in place; the
 * index serves the admin "verified / not verified" company lists, which
 * filter business accounts on the badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('business_verified_at')->nullable()->after('account_type');
            $table->foreignUlid('business_verified_by')->nullable()->after('business_verified_at')->constrained('users')->nullOnDelete();
            $table->index(['account_type', 'business_verified_at'], 'users_business_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_business_verified_idx');
            $table->dropConstrainedForeignId('business_verified_by');
            $table->dropColumn('business_verified_at');
        });
    }
};
