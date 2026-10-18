<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Featured companies lead the companies directory and the home screen in the
 * order staff give them. NULL means not featured; the index serves the
 * "featured, in order" read, which only ever touches the few featured rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('featured_position')->nullable()->after('opening_hours');
            $table->index('featured_position', 'business_profiles_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->dropIndex('business_profiles_featured_idx');
            $table->dropColumn('featured_position');
        });
    }
};
