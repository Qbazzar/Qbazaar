<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports raised by auto-moderation have no human reporter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->ulid('reporter_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('reports')->whereNull('reporter_id')->delete();

        Schema::table('reports', function (Blueprint $table): void {
            $table->ulid('reporter_id')->nullable(false)->change();
        });
    }
};
