<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the deal panel on the admin ad page, which lists an ad's newest
 * conversations and offers. Both tables only had the ad_id foreign key (on
 * conversations as the front of a unique pair), so "latest N for this ad"
 * sorted every row of a busy ad. Orders (orders_ad_status_idx) and ledger
 * lookups by order (ledger_transactions_reference_idx) are already served.
 *
 * On MySQL / MariaDB they are built with ALGORITHM=INPLACE, LOCK=NONE, so the
 * server refuses rather than blocking writes on the live tables.
 */
return new class extends Migration
{
    /** Table => [index name => columns]. */
    private const array INDEXES = [
        'conversations' => ['conversations_ad_last_msg_idx' => ['ad_id', 'last_message_at']],
        'offers' => ['offers_ad_created_idx' => ['ad_id', 'created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                $this->online() ? $this->addOnline($table, $name, $columns) : $this->addPortable($table, $name, $columns);
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach (array_keys($indexes) as $name) {
                    $blueprint->dropIndex($name);
                }
            });
        }
    }

    private function online(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @param list<string> $columns
     */
    private function addOnline(string $table, string $name, array $columns): void
    {
        $list = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$list}), ALGORITHM=INPLACE, LOCK=NONE");
    }

    /**
     * @param list<string> $columns
     */
    private function addPortable(string $table, string $name, array $columns): void
    {
        Schema::table($table, static fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }
};
