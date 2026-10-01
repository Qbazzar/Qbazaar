<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plain string instead of an enum, so the next message type does not
     * need another table rebuild.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('type', 20)->default('text')->change();
        });
    }

    public function down(): void
    {
        DB::table('messages')->where('type', 'image')->update(['type' => 'text']);

        Schema::table('messages', function (Blueprint $table): void {
            $table->enum('type', ['text', 'offer', 'system'])->default('text')->change();
        });
    }
};
