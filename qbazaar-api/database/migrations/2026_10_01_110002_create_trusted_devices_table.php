<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devices a user has proven with an SMS code (or signed up from). Sign-ins
     * from any other device must pass the new-device challenge first.
     */
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->string('label', 120)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_used_at');
            $table->timestamps();

            $table->unique(['user_id', 'device_hash']);
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
