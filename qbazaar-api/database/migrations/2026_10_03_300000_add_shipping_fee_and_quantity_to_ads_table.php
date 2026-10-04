<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the seller charges for delivery (NULL means free) and how many units
 * the ad offers. Both are read only from the ad row itself, so no index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->decimal('shipping_fee', 12, 2)->nullable()->after('shipping');
            $table->unsignedSmallInteger('quantity')->default(1)->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropColumn(['shipping_fee', 'quantity']);
        });
    }
};
