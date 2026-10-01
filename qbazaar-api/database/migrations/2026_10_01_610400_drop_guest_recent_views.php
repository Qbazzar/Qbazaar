<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guest view history now lives on the client; the API never returned it.
 * The stored guest rows are removed in batches and their index goes with
 * them, since nothing reads by session any more.
 */
return new class extends Migration
{
    private const BATCH = 5000;

    public function up(): void
    {
        do {
            $deleted = DB::table('recently_viewed')
                ->whereIn('id', DB::table('recently_viewed')->whereNull('user_id')->limit(self::BATCH)->pluck('id'))
                ->delete();
        } while ($deleted > 0);

        Schema::table('recently_viewed', function (Blueprint $table): void {
            $table->dropIndex('recently_viewed_session_viewed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('recently_viewed', function (Blueprint $table): void {
            $table->index(['session_id', 'viewed_at'], 'recently_viewed_session_viewed_idx');
        });
    }
};
