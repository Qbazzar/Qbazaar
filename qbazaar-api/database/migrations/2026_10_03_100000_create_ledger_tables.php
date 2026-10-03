<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Double-entry ledger: accounts, journal transactions and their postings.
     *
     * Transactions and entries are append-only; a correction is a reversal
     * transaction. `ledger_accounts.balance` is a cache of the account's
     * entries, written in the same database transaction as each posting, and
     * the daily reconciliation recomputes it from the entries.
     *
     * No foreign key points at users: financial records must outlive an
     * erased account.
     */
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 80)->unique();
            $table->string('owner_type', 16);
            $table->ulid('owner_id')->nullable();
            $table->string('type', 40);
            $table->string('normal_side', 6);
            $table->char('currency', 3)->default('QAR');
            $table->decimal('balance', 14, 2)->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            // `code` is the uniqueness guard for platform accounts, whose NULL owner_id a unique index ignores.
            $table->unique(['owner_type', 'owner_id', 'type'], 'ledger_accounts_owner_type_unique');
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 40);
            $table->string('idempotency_key', 191)->unique();
            $table->string('reference_type', 40)->nullable();
            $table->ulid('reference_id')->nullable();
            $table->string('memo', 255)->nullable();
            $table->string('created_by_type', 16);
            $table->ulid('created_by_id')->nullable();
            // Unique: a transaction can be reversed only once.
            $table->foreignUlid('reversal_of')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['reference_type', 'reference_id'], 'ledger_transactions_reference_idx');
            $table->index(['type', 'posted_at'], 'ledger_transactions_type_posted_idx');
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUlid('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('balance_after', 14, 2);
            $table->timestamp('created_at')->nullable();

            // Account statements, newest first.
            $table->index(['account_id', 'created_at', 'id'], 'ledger_entries_account_created_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_one_side CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
