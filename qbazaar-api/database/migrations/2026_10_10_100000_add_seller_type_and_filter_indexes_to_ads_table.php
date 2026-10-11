<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
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
 * can be retried alone. Business sellers' existing ads are filled in by
 * `php artisan ads:sync-seller-types` (the deploy runs it), in batches, not by one
 * UPDATE over the whole table here.
 *
 * Documents in the search index lack the new fields until they are imported
 * again, so the migration leaves markers that the deploy script consumes.
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

        $this->leaveDeployMarkers();
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

    /**
     * Two one-off steps for the deploy script: fill seller_type, and import the
     * ads into the search index again. A marker that cannot be written must not
     * pass silently, or the step would simply never run.
     */
    private function leaveDeployMarkers(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        foreach (['.sync-seller-types', '.reindex-ads'] as $marker) {
            $path = storage_path('app/' . $marker);

            if (file_put_contents($path, "catalog filters release\n") === false) {
                Log::warning('Could not write the deploy marker; run the step by hand (deploy/DEPLOY-CHECKLIST.md section 5)', ['marker' => $path]);
            }
        }
    }
};
