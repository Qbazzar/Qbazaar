<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who opened a private conversation from the admin panel, when and why.
 * The transcript page lists the latest openings of one conversation
 * (conversation, created_at), and a staff member's own trail reads
 * (staff, created_at). The admin audit log keeps the permanent record, so
 * these rows may go with the conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_access_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'created_at'], 'conversation_access_conversation_idx');
            $table->index(['staff_id', 'created_at'], 'conversation_access_staff_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_access_logs');
    }
};
