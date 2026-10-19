<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The moderation queue lists pending ads oldest submission first and counts
 * the overdue ones; (status, submitted_at) serves both without a sort.
 *
 * On MySQL / MariaDB the index is built with ALGORITHM=INPLACE, LOCK=NONE, so
 * the server refuses rather than blocking writes on the live table.
 */
return new class extends Migration
{
    private const string TABLE = 'ads';

    private const string INDEX = 'ads_status_submitted_idx';

    public function up(): void
    {
        if ($this->online()) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` ADD INDEX `' . self::INDEX . '` (`status`, `submitted_at`), ALGORITHM=INPLACE, LOCK=NONE');

            return;
        }

        Schema::table(self::TABLE, static fn (Blueprint $table) => $table->index(['status', 'submitted_at'], self::INDEX));
    }

    public function down(): void
    {
        Schema::table(self::TABLE, static fn (Blueprint $table) => $table->dropIndex(self::INDEX));
    }

    private function online(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
