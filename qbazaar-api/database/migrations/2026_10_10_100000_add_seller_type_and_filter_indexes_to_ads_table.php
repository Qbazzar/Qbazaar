<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog filters (ad type, shipping, seller type, condition) and their
 * facet counts read the listed ads of one status. Each gets an index that
 * starts with the listed-ad columns, so a filtered feed is read off the index
 * in publish order and a facet count scans only the matching range.
 *
 * `seller_type` copies the seller's account type onto the ad, the way
 * `seller_active` copies the status, so the filter needs no join on users.
 * The column and the indexes are separate statements so a failed index build
 * can be retried alone. Business sellers' existing ads are filled in after
 * the deploy by `php artisan ads:sync-seller-types`, in batches, not by one
 * UPDATE over the whole table here.
 *
 * Documents in the search index lack the new fields until they are imported
 * again, so the migration leaves a marker that the deploy script consumes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->string('seller_type', 16)->default('private');
        });

        Schema::table('ads', function (Blueprint $table): void {
            $table->index(['status', 'seller_active', 'ad_type', 'published_at'], 'ads_listed_ad_type_idx');
        });

        Schema::table('ads', function (Blueprint $table): void {
            $table->index(['status', 'seller_active', 'shipping', 'published_at'], 'ads_listed_shipping_idx');
        });

        Schema::table('ads', function (Blueprint $table): void {
            $table->index(['status', 'seller_active', 'seller_type', 'published_at'], 'ads_listed_seller_type_idx');
        });

        Schema::table('ads', function (Blueprint $table): void {
            $table->index(['status', 'seller_active', 'condition', 'published_at'], 'ads_listed_condition_idx');
        });

        $this->requestSearchReimport();
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_listed_ad_type_idx');
            $table->dropIndex('ads_listed_shipping_idx');
            $table->dropIndex('ads_listed_seller_type_idx');
            $table->dropIndex('ads_listed_condition_idx');
        });

        Schema::table('ads', function (Blueprint $table): void {
            $table->dropColumn('seller_type');
        });
    }

    private function requestSearchReimport(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        @file_put_contents(storage_path('app/.reindex-ads'), "seller_type and buy_now added to the ads index\n");
    }
};
