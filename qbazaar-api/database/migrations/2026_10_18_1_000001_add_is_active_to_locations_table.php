<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A location in use cannot be deleted (ads keep pointing at it), so the admin
 * deactivates it instead: it leaves the public tree and new ads, while the
 * ads already filed under it keep their place. Every existing row stays active.
 *
 * The public tree reads the whole (small, cached) table, so no index is added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
