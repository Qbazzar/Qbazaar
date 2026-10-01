<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reservation is a marker on a live ad, not a status of its own, so a
 * reserved ad keeps showing in every listing, search and count of active ads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->timestamp('reserved_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropColumn('reserved_at');
        });
    }
};
