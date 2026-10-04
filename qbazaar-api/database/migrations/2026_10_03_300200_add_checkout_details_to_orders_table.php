<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the buyer chose at checkout. The delivery address is a snapshot, so
 * editing or deleting a saved address never changes an order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('fulfillment', 20)->nullable()->after('payment_method');
            $table->json('delivery_address')->nullable()->after('fulfillment');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['fulfillment', 'delivery_address']);
        });
    }
};
