<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-backed moderation rule editor (Sprint 11).
     *
     * Until this sprint the banned-words / blocked-domain lists lived in
     * config/moderation.php only — fine for the MVP but painful to tune
     * without a deploy. This table mirrors the same two rule families plus
     * a language scope so the same English keyword can have a different
     * Arabic counterpart without forcing reviewers to maintain a single
     * polyglot row.
     *
     * Schema choices:
     *  - ULID PK so the admin URL stays in the same shape as the rest of the
     *    app (no integer leakage in the audit log).
     *  - `value` is varchar(255) — banned phrases stay short by convention,
     *    domain names are well under that.
     *  - Composite index on (type, is_active, language) so the service hot
     *    path can scan only the active rows for a single rule family.
     *  - Unique on (type, value, language) keeps the editor honest — adding
     *    "bitcoin" twice would just confuse the audit log.
     */
    public function up(): void
    {
        Schema::create('moderation_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->enum('type', ['banned_word', 'blocked_domain']);

            $table->string('value', 255);

            $table->enum('language', ['ar', 'en', 'any'])->default('any');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['type', 'is_active', 'language'], 'moderation_rules_type_active_lang_idx');
            $table->unique(['type', 'value', 'language'], 'moderation_rules_type_value_lang_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_rules');
    }
};
