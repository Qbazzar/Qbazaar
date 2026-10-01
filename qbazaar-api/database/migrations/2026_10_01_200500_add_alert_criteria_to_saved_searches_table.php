<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHUNK = 500;

    /** Same value as SavedSearch::MATCHES_NOTHING, inlined so the migration never changes with the model. */
    private const MATCHES_NOTHING = '00000000000000000000000000';

    /**
     * The structured filters of `query_params` are copied into columns so a
     * new ad is matched with one indexed query instead of decoding every
     * saved search in PHP. Keyword and custom-field filters stay in the JSON
     * and are checked only on the rows that pass this pre-filter.
     */
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table): void {
            $table->boolean('alerts_enabled')->default(true)->after('query_params');
            $table->ulid('category_id')->nullable()->after('alerts_enabled');
            $table->ulid('location_id')->nullable()->after('category_id');
            $table->string('condition', 20)->nullable()->after('location_id');
            $table->string('price_type', 20)->nullable()->after('condition');
            $table->decimal('price_min', 12, 2)->nullable()->after('price_type');
            $table->decimal('price_max', 12, 2)->nullable()->after('price_min');

            $table->index(['alerts_enabled', 'category_id'], 'saved_searches_alerts_category_idx');
        });

        DB::table('saved_searches')
            ->select(['id', 'query_params'])
            ->chunkById(self::CHUNK, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $params = json_decode((string) $row->query_params, true);

                    if (is_array($params)) {
                        DB::table('saved_searches')->where('id', $row->id)->update($this->criteria($params));
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table): void {
            $table->dropIndex('saved_searches_alerts_category_idx');
            $table->dropColumn(['alerts_enabled', 'category_id', 'location_id', 'condition', 'price_type', 'price_min', 'price_max']);
        });
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function criteria(array $params): array
    {
        return [
            'category_id' => $this->resolveId('categories', $params['category_id'] ?? null, $params['category_slug'] ?? null),
            'location_id' => $this->resolveId('locations', $params['location_id'] ?? null, $params['location_slug'] ?? null),
            'condition' => $this->choice($params['condition'] ?? null, ['new', 'like_new', 'used']),
            'price_type' => $this->choice($params['price_type'] ?? null, ['fixed', 'negotiable', 'free', 'contact']),
            'price_min' => is_numeric($params['price_min'] ?? null) ? (float) $params['price_min'] : null,
            'price_max' => is_numeric($params['price_max'] ?? null) ? (float) $params['price_max'] : null,
        ];
    }

    /**
     * A filter naming a category or location that no longer exists matches
     * nothing, like `/search` does, instead of widening to everything.
     */
    private function resolveId(string $table, mixed $id, mixed $slug): ?string
    {
        $query = match (true) {
            is_string($id) && $id !== '' => DB::table($table)->where('id', $id),
            is_string($slug) && $slug !== '' => DB::table($table)->where('slug', $slug),
            default => null,
        };

        if ($query === null) {
            return null;
        }

        $resolved = $query->value('id');

        return is_string($resolved) ? $resolved : self::MATCHES_NOTHING;
    }

    /**
     * @param list<string> $allowed
     */
    private function choice(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
};
