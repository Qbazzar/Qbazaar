<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sell-form fields from the V2 design. They are filtered through Meilisearch,
 * not SQL, so no extra index is needed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->string('ad_type', 20)->default('offering')->after('condition');
            $table->string('shipping', 20)->default('pickup_only')->after('ad_type');
            $table->string('postal_code', 10)->nullable()->after('location_id');
            $table->string('street')->nullable()->after('postal_code');
            $table->boolean('show_full_address')->default(false)->after('street');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropColumn(['ad_type', 'shipping', 'postal_code', 'street', 'show_full_address']);
        });
    }
};
