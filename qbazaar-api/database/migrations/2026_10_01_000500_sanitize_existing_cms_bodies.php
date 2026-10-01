<?php

declare(strict_types=1);

use App\Services\Content\HtmlSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bodies saved before sanitize-on-write may still hold scripts; clean them
     * once so the web never receives unsafe markup from older rows.
     */
    public function up(): void
    {
        $sanitizer = new HtmlSanitizer;

        foreach (['pages', 'help_articles'] as $table) {
            DB::table($table)->select(['id', 'body'])->orderBy('id')->chunk(100, function ($rows) use ($table, $sanitizer): void {
                foreach ($rows as $row) {
                    $body = json_decode((string) $row->body, true);

                    if (! is_array($body)) {
                        continue;
                    }

                    $clean = array_map(fn ($html): string => $sanitizer->sanitize((string) $html), $body);

                    if ($clean !== $body) {
                        DB::table($table)->where('id', $row->id)->update([
                            'body' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Stripped markup cannot be restored.
    }
};
