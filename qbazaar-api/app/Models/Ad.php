<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Moderation\ModerationResult;
use App\Enums\AdShipping;
use App\Enums\AdStatus;
use App\Enums\AdType;
use App\Enums\Condition;
use App\Enums\OfferStatus;
use App\Enums\PriceType;
use App\Enums\UserStatus;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Catalog\LocationHierarchy;
use Database\Factories\AdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
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
 * @property AdType $ad_type
 * @property AdShipping $shipping
 * @property string|null $postal_code
 * @property string|null $street
 * @property bool $show_full_address
 * @property AdStatus $status
 * @property array<string, mixed>|null $custom_fields
 * @property int $views_count
 * @property int $favorites_count
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $expiring_notified_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reserved_at
 * @property ModerationResult|null $moderation_result
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property User $user
 * @property Category $category
 * @property Location $location
 * @property Media|null $primaryImage
 * @property bool $featured
 */
class Ad extends Model implements HasMedia
{
    /** @use HasFactory<AdFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, LogsActivity, Searchable, SoftDeletes;

    /**
     * Columns read by {@see toSearchableArray()} or {@see shouldBeSearchable()}.
     *
     * @var list<string>
     */
    private const SEARCHABLE_COLUMNS = [
        'title', 'description', 'category_id', 'location_id', 'user_id', 'latitude', 'longitude',
        'price', 'price_type', 'condition', 'ad_type', 'shipping', 'postal_code', 'status',
        'reserved_at', 'published_at', 'views_count', 'custom_fields', 'deleted_at',
    ];

    protected $table = 'ads';

    /** @var string */
    protected $keyType = 'string';

    /**
     * Mirrors the column defaults so a freshly created ad serialises without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ad_type' => 'offering',
        'shipping' => 'pickup_only',
        'show_full_address' => false,
    ];

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
        'ad_type',
        'shipping',
        'postal_code',
        'street',
        'show_full_address',
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
            'ad_type' => AdType::class,
            'shipping' => AdShipping::class,
            'show_full_address' => 'boolean',
            'custom_fields' => 'array',
            'views_count' => 'integer',
            'favorites_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiring_notified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reserved_at' => 'datetime',
            'moderation_result' => ModerationResult::class,
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

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
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

    /**
     * The cover image (lowest display order), so list views load one media row per ad instead of the gallery.
     *
     * @return MorphOne<Media, $this>
     */
    public function primaryImage(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')->ofMany(
            ['order_column' => 'min', 'id' => 'min'],
            fn (Builder $query) => $query->where('collection_name', 'images'),
        );
    }

    /**
     * Gallery photos only, for existence checks; {@see getMedia()} reads the gallery itself.
     *
     * @return MorphMany<Media, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')->where('collection_name', 'images');
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
     * Active ads whose seller is active too — what public listings may show.
     * A suspended or deactivated seller keeps their ads' status untouched so
     * reactivation restores them as they were.
     *
     * @param Builder<Ad> $query
     * @return Builder<Ad>
     */
    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereHas('user', fn (Builder $seller) => $seller->where('status', UserStatus::ACTIVE->value));
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
     * Gate by status so DRAFT / PENDING / SOLD / EXPIRED rows — and ads of a
     * suspended or deactivated seller — never appear in search results.
     * Scout's save observer is the single sync path: it indexes the ad when
     * this turns true and removes it when it turns false.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isPubliclyListed();
    }

    public function isReserved(): bool
    {
        return $this->reserved_at !== null;
    }

    /** Instance counterpart of {@see scopePubliclyListed()}. */
    public function isPubliclyListed(): bool
    {
        return $this->status === AdStatus::ACTIVE && $this->hasActiveSeller();
    }

    public function hasActiveSeller(): bool
    {
        /** @var User|null $seller */
        $seller = $this->user;

        return $seller?->status === UserStatus::ACTIVE;
    }

    /**
     * @param Builder<Ad> $query
     * @return Builder<Ad>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['user', 'category', 'location'])->withExists('images as has_images_flag');
    }

    /**
     * Queued Scout jobs index ads in batches, where lazy loading these relations is an N+1.
     *
     * @param BaseCollection<int, Ad> $models
     * @return BaseCollection<int, Ad>
     */
    public function makeSearchableUsing(BaseCollection $models): BaseCollection
    {
        return (new EloquentCollection($models->all()))
            ->loadMissing(['user', 'category', 'location'])
            ->loadExists('images as has_images_flag');
    }

    /**
     * Saves that touch none of these columns (moderation notes, expiry
     * warnings, counters…) leave the search document as it is, so they
     * queue no Scout job. Status and reservation are here because they
     * decide whether the ad is listed at all.
     */
    public function searchIndexShouldBeUpdated(): bool
    {
        $insertedNow = $this->wasRecentlyCreated && $this->getChanges() === [];

        return $insertedNow || $this->wasChanged(self::SEARCHABLE_COLUMNS);
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
        // The belongsTo accessors are typed non-null, but Eloquent returns null
        // for an orphaned/missing foreign key. Pin them as nullable so indexing
        // and scout:import degrade to null instead of throwing "property slug on null".
        /** @var Category|null $category */
        $category = $this->category;
        /** @var Location|null $location */
        $location = $this->location;

        $document = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => Str::limit((string) $this->description, 500, ''),
            'category_id' => $this->category_id,
            'category_slug' => $category?->slug,
            'category_path' => app(CategoryHierarchy::class)->pathTo($this->category_id),
            'location_id' => $this->location_id,
            'location_slug' => $location?->slug,
            'location_path' => app(LocationHierarchy::class)->pathTo($this->location_id),
            'user_id' => $this->user_id,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_type' => $this->price_type->value,
            'condition' => $this->condition?->value,
            'ad_type' => $this->ad_type->value,
            'shipping' => $this->shipping->value,
            'postal_code' => $this->postal_code,
            'status' => $this->status->value,
            'is_reserved' => $this->isReserved(),
            'published_at' => $this->published_at?->getTimestamp(),
            'created_at_ts' => $this->created_at instanceof Carbon ? $this->created_at->getTimestamp() : null,
            'has_images' => $this->hasImages(),
            'views_count' => (int) $this->views_count,
            // Category-specific attributes, indexed as a nested object so the
            // search can filter `custom_fields.year >= 2015` etc. Numeric-looking
            // strings are cast to real numbers so range filters compare correctly.
            'custom_fields' => $this->customFieldsForSearch(),
        ];

        // Meilisearch rejects a malformed _geo, so ads with no known position leave it out entirely.
        $geo = $this->geoPoint($location);
        if ($geo !== null) {
            $document['_geo'] = $geo;
        }

        return $document;
    }

    /**
     * Batch indexing preloads the flag through {@see images()}; a single
     * save falls back to one indexed EXISTS query.
     */
    private function hasImages(): bool
    {
        if (array_key_exists('has_images_flag', $this->attributes)) {
            return (bool) $this->attributes['has_images_flag'];
        }

        return $this->images()->exists();
    }

    /**
     * The ad's own pin, or the centre of its location when the seller did not drop one.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function geoPoint(?Location $location): ?array
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude];
        }

        if ($location?->lat !== null && $location->lng !== null) {
            return ['lat' => (float) $location->lat, 'lng' => (float) $location->lng];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function customFieldsForSearch(): array
    {
        $fields = is_array($this->custom_fields) ? $this->custom_fields : [];

        return array_map(
            static fn (mixed $value): mixed => is_string($value) && is_numeric($value) ? $value + 0 : $value,
            $fields,
        );
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Media — Spatie MediaLibrary integration.
     *
     *  Every size is rendered on the queue so an upload request does no
     *  image decoding. MediaResource falls back to the signed original URL
     *  until a variant exists.
     * ──────────────────────────────────────────────────────────────────*/
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->storeConversionsOnDisk((string) config('qbazaar.uploads.public_disk'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->queued()
            ->performOnCollections('images')
            ->fit(Fit::Crop, 200, 200);

        $this->addMediaConversion('medium')
            ->queued()
            ->performOnCollections('images')
            ->fit(Fit::Contain, 640, 640);

        $this->addMediaConversion('large')
            ->queued()
            ->performOnCollections('images')
            ->fit(Fit::Contain, 1024, 1024);

        $this->addMediaConversion('original_webp')
            ->queued()
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
