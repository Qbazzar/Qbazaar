<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per business account, read by primary key (the user id) on the
 * profile page and eager-loaded per page in the companies directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('business_name', 120)->nullable();
            $table->text('about')->nullable();
            $table->string('legal_name', 160)->nullable();
            $table->string('commercial_registration_number', 40)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->json('opening_hours')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
