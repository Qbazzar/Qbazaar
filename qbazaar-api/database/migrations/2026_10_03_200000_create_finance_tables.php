<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bank accounts, commission settlements and withdrawals. Money itself
     * lives in the ledger; these rows are the requests the admin reviews.
     *
     *  - The IBAN is stored encrypted; `iban_hash` (an HMAC) finds a
     *    duplicate without decrypting and `iban_last4` is what responses show.
     *  - `pending_user_id` mirrors `user_id` while a settlement is pending and
     *    is NULL otherwise; its unique index allows one pending settlement
     *    per seller even when two requests race.
     *  - A withdrawal keeps its own encrypted copy of the IBAN and holder, so
     *    deleting the bank account never changes where a payout went.
     *  - Settlements and withdrawals are financial records: like orders,
     *    they survive an erased account with the user set to NULL.
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('holder_name', 120);
            $table->text('iban');
            $table->char('iban_hash', 64);
            $table->string('iban_last4', 4);
            $table->string('bank_name', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'iban_hash'], 'bank_accounts_user_iban_unique');
        });

        Schema::create('commission_settlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('pending_user_id')->nullable()->unique();
            $table->string('method', 20);
            $table->string('status', 20);
            $table->decimal('amount', 14, 2);
            $table->string('bank_reference', 100)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'commission_settlements_user_created_idx');
            $table->index(['status', 'created_at'], 'commission_settlements_status_created_idx');
        });

        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('status', 20);
            $table->decimal('amount', 14, 2);
            $table->string('holder_name', 120);
            $table->text('iban');
            $table->string('iban_last4', 4);
            $table->string('transfer_reference', 100)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'withdrawals_user_created_idx');
            $table->index(['status', 'created_at'], 'withdrawals_status_created_idx');
            $table->index(['user_id', 'status'], 'withdrawals_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('commission_settlements');
        Schema::dropIfExists('bank_accounts');
    }
};
