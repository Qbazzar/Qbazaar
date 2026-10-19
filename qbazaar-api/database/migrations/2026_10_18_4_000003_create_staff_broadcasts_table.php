<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications staff send to a segment of users (AD-17.15).
 *
 *  - The history page lists the newest first (created_at).
 *  - The per-sender hourly and daily caps count a sender's recent rows
 *    (sender_id, created_at).
 *  - chunks_total / chunks_done track the delayed delivery chunks; the last
 *    chunk to finish marks the broadcast sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_broadcasts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('segment', 20);
            $table->foreignUlid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('title_ar', 120);
            $table->text('body_ar');
            $table->string('title_en', 120)->nullable();
            $table->text('body_en')->nullable();
            $table->boolean('send_email')->default(false);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('chunks_total')->nullable();
            $table->unsignedInteger('chunks_done')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('created_at', 'staff_broadcasts_created_idx');
            $table->index(['sender_id', 'created_at'], 'staff_broadcasts_sender_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_broadcasts');
    }
};
