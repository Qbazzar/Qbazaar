<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user asking to become a verified business: the legal details they
 * declare, their documents (media, private disk) and the staff review.
 *
 * `open_user_id` equals `user_id` while the application waits for staff or
 * for the applicant (pending, needs_info) and is NULL otherwise; its unique
 * index allows one open application per user even under concurrent submits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->ulid('open_user_id')->nullable()->unique('business_applications_open_user_unique');
            $table->string('status', 20);
            $table->string('business_name', 120);
            $table->string('legal_name', 160);
            $table->string('commercial_registration_number', 40);
            $table->string('applicant_note', 1000)->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Set on every (re)submission: the review queue is first in, first out on it.
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['status', 'submitted_at'], 'business_applications_queue_idx');
            $table->index(['user_id', 'created_at'], 'business_applications_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_applications');
    }
};
