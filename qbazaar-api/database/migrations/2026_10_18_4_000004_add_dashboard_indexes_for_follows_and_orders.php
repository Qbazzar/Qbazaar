<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for AD-17.6 and AD-17.7:
 *
 *  - follows (created_at): the dashboard's windowed follow count and its
 *    30-day series filter follows by time only.
 *  - orders (status, created_at): the admin orders list filters one status
 *    and pages newest first.
 *
 * These run on live tables. On MySQL / MariaDB they are created with
 * ALGORITHM=INPLACE, LOCK=NONE, so the server refuses (and the migration
 * fails) rather than silently blocking writes while it builds them.
 */
return new class extends Migration
{
    /** Table => [index name => columns]. */
    private const array INDEXES = [
        'follows' => ['follows_created_at_idx' => ['created_at']],
        'orders' => ['orders_status_created_idx' => ['status', 'created_at']],
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
