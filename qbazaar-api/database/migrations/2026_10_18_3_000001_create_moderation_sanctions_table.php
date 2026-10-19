<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of staff sanctions: what was done to which ad or account,
 * the preset reason, the internal note and the report it answered, if any.
 *
 * The subject index serves "latest sanction on this ad / account" on the
 * admin pages; the foreign keys index the staff and report lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_sanctions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('subject_type', 16);
            $table->ulid('subject_id');
            $table->string('action', 32);
            $table->string('reason', 32);
            $table->text('note')->nullable();
            $table->foreignUlid('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'moderation_sanctions_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_sanctions');
    }
};
