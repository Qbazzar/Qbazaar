<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the admin home's windowed counts. Each dashboard query filters a
 * table by a time range or a type; without these they scan the whole table
 * (messages, favorites and orders are the large ones).
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
            'orders_created_at_idx' => ['created_at'],
            'orders_status_completed_idx' => ['status', 'completed_at'],
        ],
        'ad_promotions' => ['ad_promotions_activated_at_idx' => ['activated_at']],
        'ledger_accounts' => ['ledger_accounts_type_balance_idx' => ['type', 'balance']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $name => $columns) {
                    $blueprint->index($columns, $name);
                }
            });
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
};
