<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The safe delete of a category or a location counts the saved searches that
 * point at it. The alert index leads with `alerts_enabled`, so a count by
 * category or by location alone would scan the table without these.
 *
 * On MySQL / MariaDB they are created with ALGORITHM=INPLACE, LOCK=NONE, so
 * the server refuses rather than blocking writes while it builds them.
 */
return new class extends Migration
{
    /** Index name => column. */
    private const array INDEXES = [
        'saved_searches_category_idx' => 'category_id',
        'saved_searches_location_idx' => 'location_id',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $column) {
            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `saved_searches` ADD INDEX `{$name}` (`{$column}`), ALGORITHM=INPLACE, LOCK=NONE");

                continue;
            }

            Schema::table('saved_searches', static fn (Blueprint $table) => $table->index([$column], $name));
        }
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table): void {
            foreach (array_keys(self::INDEXES) as $name) {
                $table->dropIndex($name);
            }
        });
    }
};
