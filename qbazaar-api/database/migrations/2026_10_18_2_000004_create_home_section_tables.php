<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff curation of the home screen rails. A section without a settings row
 * runs in automatic mode; the hand-picked ads are kept when the section is
 * switched back to automatic, so switching is never destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_settings', function (Blueprint $table): void {
            $table->string('section', 32)->primary();
            $table->string('mode', 16);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('home_section_items', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 32);
            $table->foreignUlid('ad_id')->constrained('ads')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignUlid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section', 'ad_id'], 'home_section_items_section_ad_unique');
            $table->index(['section', 'position'], 'home_section_items_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
        Schema::dropIfExists('home_section_settings');
    }
};
