<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the admin home's windowed counts. Each dashboard query filters a
 * table by a time range or a type; without these they scan the whole table
 * (messages, favorites and orders are the large ones).
 *
 * These run on live tables. On MySQL / MariaDB they are created with
 * ALGORITHM=INPLACE, LOCK=NONE, so the server refuses (and the migration
 * fails) rather than silently blocking writes while it rebuilds.
 */
return new class extends Migration
{
    /** Table => [index name => columns]. */
    private const array INDEXES = [
        'messages' => ['messages_created_at_idx' => ['created_at']],
        'conversations' => ['conversations_created_at_idx' => ['created_at']],
        'offers' => ['offers_created_at_idx' => ['created_at']],
        'purchase_requests' => ['purchase_requests_created_at_idx' => ['created_at']],
        'favorites' => ['favorites_created_at_idx' => ['created_at']],
        'saved_searches' => ['saved_searches_created_at_idx' => ['created_at']],
        'orders' => [
            'orders_created_status_idx' => ['created_at', 'status'],
            'orders_status_completed_idx' => ['status', 'completed_at'],
        ],
        'ad_promotions' => ['ad_promotions_status_activated_idx' => ['status', 'activated_at']],
        'ledger_accounts' => ['ledger_accounts_type_balance_idx' => ['type', 'balance']],
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
