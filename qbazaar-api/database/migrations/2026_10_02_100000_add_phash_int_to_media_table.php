<?php

declare(strict_types=1);

use App\Services\Media\PerceptualHashService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'media_model_phash_idx';

    /**
     * The perceptual hash as a 64-bit integer so the duplicate check can use
     * BIT_COUNT in SQL. Signed BIGINT because PHP has no unsigned 64-bit int:
     * the bit pattern is identical and MySQL's bit operators work on it as
     * unsigned. The index starts with the morph columns, so after the ads
     * prefilter each candidate ad's hashes are read from the index alone.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->bigInteger('phash_int')->nullable()->after('phash');
            $table->index(['model_type', 'model_id', 'phash_int'], self::INDEX);
        });

        $hasher = app(PerceptualHashService::class);

        DB::table('media')
            ->whereNotNull('phash')
            ->whereNull('phash_int')
            ->select(['id', 'phash'])
            ->chunkById(1000, function ($rows) use ($hasher): void {
                foreach ($rows as $row) {
                    $value = $hasher->toInteger((string) $row->phash);

                    if ($value !== null) {
                        DB::table('media')->where('id', $row->id)->update(['phash_int' => $value]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
            $table->dropColumn('phash_int');
        });
    }
};
