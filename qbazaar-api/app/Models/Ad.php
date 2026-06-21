<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\OfferStatus;
use App\Enums\PriceType;
use App\Events\Ads\AdRejected;
use App\Http\Resources\Api\V1\Media\MediaResource;
use Database\Factories\AdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $user_id
 * @property string $category_id
 * @property string $location_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $title
 * @property string $description
 * @property string|null $price
 * @property PriceType $price_type
 * @property string $currency
 * @property Condition|null $condition
 * @property AdStatus $status
 * @property array<string, mixed>|null $custom_fields
 * @property int $views_count
 * @property int $favorites_count
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property User $user
 * @property Category $category
 * @property Location $location
 * @property bool $featured
 */
class Ad extends Model implements HasMedia
{
    /** @use HasFactory<AdFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, LogsActivity, Searchable, SoftDeletes;

    protected $table = 'ads';

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'location_id',
        'latitude',
        'longitude',
        'title',
        'description',
        'price',
        'price_type',
        'currency',
        'condition',
        'status',
        'custom_fields',
        'views_count',
        'favorites_count',
        'published_at',
        'expires_at',
        'featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdStatus::class,
            'price_type' => PriceType::class,
            'condition' => Condition::class,
            'custom_fields' => 'array',
            'views_count' => 'integer',
            'favorites_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'price' => 'decimal:2',
            'featured' => 'boolean',
        ];
    }

    /**
     * Spatie activity-log configuration. We log the user-facing attributes
     * (title / description / price / status / category / location) under the
     * `ad` log name so admin queries scope cleanly.
     *
     * `logOnlyDirty()` ensures we only persist a row when one of the watched
     * columns actually changed — avoids one log entry per touch / counter bump.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title',
                'description',
                'price',
                'status',
                'category_id',
                'location_id',
            ])
            ->logOnlyDirty()
            ->useLogName('ad')
            ->dontLogEmptyChanges();
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Relations
     * ──────────────────────────────────────────────────────────────────*/

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The accepted offer (if any) — identifies the buyer of a sold ad.
     *
     * @return HasOne<Offer, $this>
     */
    public function acceptedOffer(): HasOne
    {
        return $this->hasOne(Offer::class)
            ->where('status', OfferStatus::ACCEPTED->value)
            ->latest('accepted_at');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Query scopes — used by feed / dashboard / browse endpoints.
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * Restrict to publicly-visible ads.
     *
     * @param Builder<Ad> $query
     * @return Builder<Ad>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AdStatus::ACTIVE->value);
    }

    /**
     * Restrict to ads owned by a given user. We accept the model to keep
     * call sites self-documenting (`->forUser($user)`) and to guarantee
     * we never accept a raw string ID by accident.
     *
     * @param Builder<Ad> $query
     * @return Builder<Ad>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Latest-published first — the canonical ordering for the public feed.
     *
     * @param Builder<Ad> $query
     * @return Builder<Ad>
     */
    public function scopeOrderedForFeed(Builder $query): Builder
    {
        return $query->orderByDesc('published_at');
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Lifecycle transitions — keep the rules in one place so the
     *  controllers stay declarative.
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * Publish a draft and transition straight to ACTIVE.
     *
     * Used after auto-moderation clears the ad (see PublishAdController).
     * Calls `searchable()` explicitly so the ad is pushed to Meilisearch
     * the moment it transitions to ACTIVE — `shouldBeSearchable()` would
     * otherwise rely on the next ::save() to trigger Scout's observer, and
     * that lag is observable to the seller's "did my ad go live yet" flow.
     */
    public function publish(): void
    {
        $lifetimeDays = (int) config('qbazaar.ads.lifetime_days', 30);

        $this->forceFill([
            'status' => AdStatus::ACTIVE,
            'published_at' => now(),
            'expires_at' => now()->addDays($lifetimeDays),
        ])->save();

        $this->searchable();
    }

    /**
     * Park a flagged draft in PENDING — awaiting manual review.
     *
     * Caller fires {@see AdRejected} so the seller receives
     * the rejection notification AND the search index is kept clean.
     * Setting `published_at` to null keeps the public feed safe.
     */
    public function holdForReview(): void
    {
        $this->forceFill([
            'status' => AdStatus::PENDING,
            'published_at' => null,
            'expires_at' => null,
        ])->save();

        // Defensive: a PENDING ad must never appear in search even if a
        // previous publish leaked it through. Scout's shouldBeSearchable()
        // would handle this on save, but we call it explicitly for the same
        // reason as publish().
        $this->unsearchable();
    }

    /**
     * Mark a previously expired ad as EXPIRED + drop it from search.
     * Invoked by the daily expiry job; kept on the model so the rule lives
     * with the other lifecycle transitions.
     */
    public function markExpired(): void
    {
        $this->forceFill(['status' => AdStatus::EXPIRED])->save();

        $this->unsearchable();
    }

    /**
     * Mark the ad as sold. Only ACTIVE and EXPIRED ads can be sold —
     * enforced by AdPolicy::markSold().
     *
     * Sold ads must drop out of search results immediately; we force the
     * unindex rather than waiting for `shouldBeSearchable()` to win the
     * next save cycle.
     */
    public function markSold(): void
    {
        $this->forceFill(['status' => AdStatus::SOLD])->save();

        $this->unsearchable();
    }

    /**
     * Extend expiry by another lifetime window. If the ad has already
     * expired, flip it back to ACTIVE in the same call so the seller
     * doesn't need a separate "republish" step.
     *
     * Re-indexes the ad when it flips back to ACTIVE so renewed listings
     * reappear in search without waiting for a re-save.
     */
    public function renew(): void
    {
        $lifetimeDays = (int) config('qbazaar.ads.lifetime_days', 30);

        $base = $this->expires_at !== null && $this->expires_at->isFuture()
            ? $this->expires_at
            : now();

        $wasExpired = $this->status === AdStatus::EXPIRED;

        $this->forceFill([
            'expires_at' => $base->copy()->addDays($lifetimeDays),
            'status' => $wasExpired
                ? AdStatus::ACTIVE
                : $this->status,
        ])->save();

        if ($wasExpired) {
            $this->searchable();
        }
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Scout / Meilisearch — Sprint 6.
     *
     *  Only ACTIVE ads are searchable; other statuses are skipped via
     *  `shouldBeSearchable()`. The payload is deliberately denormalised
     *  (category_slug / location_slug / has_images) so the frontend can
     *  render result cards without an extra round-trip.
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * Name of the Meilisearch index for this model. Honours SCOUT_PREFIX so
     * staging / prod / per-tenant deployments stay isolated when they share
     * a single Meilisearch instance.
     */
    public function searchableAs(): string
    {
        return ((string) config('scout.prefix', '')) . 'ads_index';
    }

    /**
     * Gate by status so DRAFT / PENDING / SOLD / EXPIRED rows never appear
     * in search results. Scout consults this on every observer-driven sync;
     * we also call `searchable()` / `unsearchable()` explicitly in the
     * lifecycle methods above to avoid relying on a follow-up save.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === AdStatus::ACTIVE;
    }

    /**
     * Shape sent to Meilisearch. Kept tight on purpose:
     *  - `description` is truncated to 500 chars — search relevance peaks
     *    long before that; the extra bytes just bloat the index.
     *  - timestamps are stored as unix-int so Meili can sort + range-filter
     *    without parsing ISO strings on every query.
     *  - `has_images` is materialised so filter UI can show "with photos
     *    only" without an extra DB lookup per result.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Avoid loading every Media row when we only need a count — the
        // hot path runs on every save and the underlying query is indexed.
        $hasImages = $this->media()->where('collection_name', 'images')->exists();

        // The belongsTo accessors are typed non-null, but Eloquent returns null
        // for an orphaned/missing foreign key. Pin them as nullable so indexing
        // (and the database-driver search, which runs toSearchableArray on every
        // row) degrades to null instead of throwing "property slug on null".
        /** @var Category|null $category */
        $category = $this->category;
        /** @var Location|null $location */
        $location = $this->location;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => Str::limit((string) $this->description, 500, ''),
            'category_id' => $this->category_id,
            'category_slug' => $category?->slug,
            'location_id' => $this->location_id,
            'location_slug' => $location?->slug,
            'user_id' => $this->user_id,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_type' => $this->price_type->value,
            'condition' => $this->condition?->value,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->getTimestamp(),
            'created_at_ts' => $this->created_at instanceof Carbon ? $this->created_at->getTimestamp() : null,
            'has_images' => $hasImages,
        ];
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Media — Spatie MediaLibrary integration.
     *
     *  Conversions are non-queued so the upload response can already cite
     *  every variant. BlurHash + (future) pHash run async via
     *  ProcessAdImagesJob because they're cheap-but-not-instant.
     * ──────────────────────────────────────────────────────────────────*/
    public function registerMediaCollections(): void
    {
        // No singleFile() — ads carry up to 10 images. The count cap is
        // enforced in UploadImagesRequest, not here, so a future bulk
        // import can opt out without changing the model contract.
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->nonQueued()
            ->performOnCollections('images')
            ->fit(Fit::Crop, 200, 200);

        $this->addMediaConversion('medium')
            ->nonQueued()
            ->performOnCollections('images')
            ->fit(Fit::Contain, 640, 640);

        $this->addMediaConversion('large')
            ->nonQueued()
            ->performOnCollections('images')
            ->fit(Fit::Contain, 1024, 1024);

        $this->addMediaConversion('original_webp')
            ->nonQueued()
            ->performOnCollections('images')
            ->fit(Fit::Contain, 1920, 1920)
            ->format('webp');
    }

    /**
     * Plain-array form of the image list, ordered by display order.
     * Lives here (rather than on the resource) so admin / Filament screens
     * can render the same payload without re-implementing the mapping.
     *
     * Delegates each row to {@see MediaResource} so the URL semantics
     * (expiring signed original + public conversion sizes) are defined in
     * exactly one place.
     *
     * @return list<array<string, mixed>>
     */
    public function imagesPayload(): array
    {
        $request = request();

        $rows = $this->getMedia('images')
            ->sortBy('order_column')
            ->map(static fn (Media $m): array => (new MediaResource($m))->toArray($request))
            ->all();

        return array_values($rows);
    }
}
