<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `user_blocks` pivot shipped with only `created_at`. Laravel's
     * BelongsToMany::attach() writes `updated_at` unconditionally once the
     * relationship is "timed" — and `blockedUsers()->withPivot('created_at')`
     * makes it timed — so the insert references a column that never existed.
     * The result: every block attempt fails with
     * "table user_blocks has no column named updated_at".
     *
     * We add the column (nullable, after created_at) rather than fight the
     * framework's attach path. We still only ever read `created_at`; this
     * column exists purely so the write succeeds.
     */
    public function up(): void
    {
        Schema::table('user_blocks', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_blocks', function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });
    }
};
