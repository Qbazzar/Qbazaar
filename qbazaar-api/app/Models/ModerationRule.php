<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationRuleLanguage;
use App\Enums\ModerationRuleType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A single moderation rule row, editable from the Filament admin panel.
 *
 *  - Cache invalidation lives here (not on the resource pages) so any code
 *    path that mutates a rule — seeder, console command, future API — keeps
 *    the lookup cache in sync without duplicating logic.
 *  - The cache keys mirror those consumed by ModerationRulesService so the
 *    service can hot-load the active rule list with a single Redis round-trip.
 *
 * @property string $id
 * @property ModerationRuleType $type
 * @property string $value
 * @property ModerationRuleLanguage $language
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ModerationRule extends Model
{
    use HasUlids;

    /** Cache TTL — matches the 1h refresh window agreed in the sprint brief. */
    public const int CACHE_TTL_SECONDS = 3600;

    public const string CACHE_KEY_BANNED_WORDS = 'moderation.banned_words';

    public const string CACHE_KEY_BLOCKED_DOMAINS = 'moderation.blocked_domains';

    protected $table = 'moderation_rules';

    /** @var string */
    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'type',
        'value',
        'language',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ModerationRuleType::class,
            'language' => ModerationRuleLanguage::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Wipe the two caches that the service consumes. Called automatically on
     * the Eloquent saved/deleted hooks so the admin UI doesn't need to know
     * the cache key shape.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY_BANNED_WORDS);
        Cache::forget(self::CACHE_KEY_BLOCKED_DOMAINS);
    }

    protected static function booted(): void
    {
        // The three lifecycle hooks cover every write path we care about. We
        // intentionally do not hook `retrieved` — that's a read-only event.
        static::saved(static fn () => self::flushCache());
        static::deleted(static fn () => self::flushCache());
    }
}
