<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per requested personal-data export. `downloaded_at` makes the
     * emailed link single-use; `expires_at` drives pruning of the file.
     */
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'data_exports_user_created_idx');
            $table->index('expires_at', 'data_exports_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
