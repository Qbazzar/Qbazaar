<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a perceptual hash column to the media table for duplicate detection.
     *
     * Why a real column instead of a custom_property?
     *   Task 1.3 queries duplicates with BIT_COUNT(CONV(a,16,10) ^ CONV(b,16,10))
     *   in SQL. Custom properties live in a JSON blob and cannot use that
     *   expression efficiently. A CHAR(16) real column lets MySQL use the hex
     *   string directly with CONV() and index-assists on exact lookups.
     *
     * CHAR(16) not VARCHAR: every hash is exactly 16 lowercase hex chars
     *   (64-bit dHash). Fixed width avoids the 1–2 byte length prefix MySQL
     *   stores for VARCHAR and makes equality scans slightly cheaper.
     *
     * Nullable: the column is filled asynchronously by ProcessAdImagesJob.
     *   Rows created before the job runs (or whose file is unreadable) keep
     *   null; the application handles that gracefully — matching logic skips
     *   null hashes.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->char('phash', 16)->nullable()->index()->after('responsive_images');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(['phash']);
            $table->dropColumn('phash');
        });
    }
};
