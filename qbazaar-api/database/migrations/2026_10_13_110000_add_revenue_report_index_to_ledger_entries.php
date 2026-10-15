<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A covering index for the revenue report. Its aggregates filter one account
 * by a time range and sum debit and credit per transaction; with these
 * columns in the index the database answers from the index alone instead of
 * a primary-key lookup per entry. The plain (account_id, created_at, id)
 * index stays: account statements read it and need the id order.
 *
 * It runs on a live table, so MySQL / MariaDB build it with
 * ALGORITHM=INPLACE, LOCK=NONE and refuse rather than block writes.
 */
return new class extends Migration
{
    private const string TABLE = 'ledger_entries';

    private const string INDEX = 'ledger_entries_revenue_cover_idx';

    private const array COLUMNS = ['account_id', 'created_at', 'transaction_id', 'debit', 'credit'];

    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->index(self::COLUMNS, self::INDEX));

            return;
        }

        $list = implode(', ', array_map(static fn (string $column): string => "`{$column}`", self::COLUMNS));

        DB::statement('ALTER TABLE `' . self::TABLE . '` ADD INDEX `' . self::INDEX . "` ({$list}), ALGORITHM=INPLACE, LOCK=NONE");
    }

    public function down(): void
    {
        Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex(self::INDEX));
    }
};
