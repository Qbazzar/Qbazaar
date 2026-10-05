<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `handed_over_at` is when the seller confirmed the handover: it starts
     * the buyer's window to report a problem and, for escrow orders, the
     * countdown to the automatic release. The dispute columns keep who
     * opened it and why, and the admin's ruling.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('handed_over_at')->nullable()->after('awaiting_handover_at');
            $table->foreignUlid('disputed_by')->nullable()->after('disputed_at')->constrained('users')->nullOnDelete();
            $table->string('dispute_reason', 1000)->nullable()->after('disputed_by');
            $table->string('dispute_resolution', 20)->nullable()->after('dispute_reason');
            $table->string('dispute_resolution_note', 1000)->nullable()->after('dispute_resolution');
            $table->foreignUlid('dispute_resolved_by')->nullable()->after('dispute_resolution_note')->constrained('users')->nullOnDelete();
            $table->timestamp('dispute_resolved_at')->nullable()->after('dispute_resolved_by');

            $table->index(['status', 'handed_over_at'], 'orders_status_handed_over_idx');
            $table->index(['status', 'disputed_at'], 'orders_status_disputed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_status_handed_over_idx');
            $table->dropIndex('orders_status_disputed_idx');
            $table->dropConstrainedForeignId('disputed_by');
            $table->dropConstrainedForeignId('dispute_resolved_by');
            $table->dropColumn(['handed_over_at', 'dispute_reason', 'dispute_resolution', 'dispute_resolution_note', 'dispute_resolved_at']);
        });
    }
};
