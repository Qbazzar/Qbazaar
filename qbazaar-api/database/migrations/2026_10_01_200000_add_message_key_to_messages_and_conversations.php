<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System and offer bubbles are stored as a translation key + params so
     * each reader sees them in their own language. The conversation keeps
     * the key of its last message too, so the inbox preview translates
     * without reading `messages`.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('message_key', 64)->nullable()->after('body');
            $table->json('params')->nullable()->after('message_key');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('last_message_key', 64)->nullable()->after('last_message_preview');
            $table->json('last_message_params')->nullable()->after('last_message_key');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['last_message_key', 'last_message_params']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn(['message_key', 'params']);
        });
    }
};
