<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog filters (ad type, shipping, seller type, condition) and their
 * facet counts read the listed ads of one status. Each gets an index that
 * starts with the listed-ad columns, so a filtered feed is read off the index
 * in publish order and a facet count scans only the matching range.
 *
 * `seller_type` copies the seller's account type onto the ad, the way
 * `seller_active` copies the status, so the filter needs no join on users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->string('seller_type', 16)->default('private')->after('seller_active');
            $table->index(['status', 'seller_active', 'ad_type', 'published_at'], 'ads_listed_ad_type_idx');
            $table->index(['status', 'seller_active', 'shipping', 'published_at'], 'ads_listed_shipping_idx');
            $table->index(['status', 'seller_active', 'seller_type', 'published_at'], 'ads_listed_seller_type_idx');
            $table->index(['status', 'seller_active', 'condition', 'published_at'], 'ads_listed_condition_idx');
        });

        DB::table('ads')
            ->whereIn('user_id', DB::table('users')->select('id')->where('account_type', 'business'))
            ->update(['seller_type' => 'business']);
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_listed_ad_type_idx');
            $table->dropIndex('ads_listed_shipping_idx');
            $table->dropIndex('ads_listed_seller_type_idx');
            $table->dropIndex('ads_listed_condition_idx');
            $table->dropColumn('seller_type');
        });
    }
};
