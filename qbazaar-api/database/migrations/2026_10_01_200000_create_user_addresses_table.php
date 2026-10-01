<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved delivery addresses, at most `qbazaar.account.max_addresses` per
     * user with exactly one default. `(user_id, is_default)` serves both the
     * owner's list and the "current default" lookup.
     */
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('full_name', 80);
            $table->string('phone', 20)->nullable();
            $table->string('street', 120);
            $table->string('house_number', 20);
            $table->string('supplement', 120)->nullable();
            $table->string('city', 80);
            $table->string('postal_code', 20)->nullable();
            $table->foreignUlid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default'], 'user_addresses_user_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
