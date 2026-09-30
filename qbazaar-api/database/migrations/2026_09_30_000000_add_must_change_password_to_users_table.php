<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Set on accounts provisioned with a known password (the seeded admin); the
 * admin panel only lets them reach their profile until they pick a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        // Installs seeded before this change still carry the default admin password.
        $admin = DB::table('users')->where('email', 'admin@qbazaar.qa')->first(['id', 'password']);

        if ($admin !== null && Hash::check('password', (string) $admin->password)) {
            DB::table('users')->where('id', $admin->id)->update(['must_change_password' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
