<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\PriceType;
use Database\Factories\AdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $user_id
 * @property string $category_id
 * @property string $location_id
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
 */
class Ad extends Model implements HasMedia
{
    /** @use HasFactory<AdFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, SoftDeletes;

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
        ];
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Relations
     * ──────────────────────────────────────────────────────────────────*/

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Publish a draft. In Wave A we skip the PENDING auto-moderation step
     * and transition straight to ACTIVE; Sprint 5 nice-to-have will add the
     * moderation hop without changing the public contract.
     */
    public function publish(): void
    {
        $lifetimeDays = (int) config('qbazaar.ads.lifetime_days', 30);

        $this->forceFill([
            'status' => AdStatus::ACTIVE,
            'published_at' => now(),
            'expires_at' => now()->addDays($lifetimeDays),
        ])->save();
    }

    /**
     * Mark the ad as sold. Only ACTIVE and EXPIRED ads can be sold —
     * enforced by AdPolicy::markSold().
     */
    public function markSold(): void
    {
        $this->forceFill(['status' => AdStatus::SOLD])->save();
    }

    /**
     * Extend expiry by another lifetime window. If the ad has already
     * expired, flip it back to ACTIVE in the same call so the seller
     * doesn't need a separate "republish" step.
     */
    public function renew(): void
    {
        $lifetimeDays = (int) config('qbazaar.ads.lifetime_days', 30);

        $base = $this->expires_at !== null && $this->expires_at->isFuture()
            ? $this->expires_at
            : now();

        $this->forceFill([
            'expires_at' => $base->copy()->addDays($lifetimeDays),
            'status' => $this->status === AdStatus::EXPIRED
                ? AdStatus::ACTIVE
                : $this->status,
        ])->save();
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
     * @return list<array<string, mixed>>
     */
    public function imagesPayload(): array
    {
        $ordered = $this->getMedia('images')->sortBy('order_column')->values();

        $rows = [];
        foreach ($ordered as $m) {
            $rows[] = [
                'id' => $m->getKey(),
                'collection' => $m->collection_name,
                'url' => $m->getUrl(),
                'sizes' => [
                    'thumbnail' => $m->hasGeneratedConversion('thumbnail') ? $m->getUrl('thumbnail') : $m->getUrl(),
                    'medium' => $m->hasGeneratedConversion('medium') ? $m->getUrl('medium') : $m->getUrl(),
                    'large' => $m->hasGeneratedConversion('large') ? $m->getUrl('large') : $m->getUrl(),
                    'original_webp' => $m->hasGeneratedConversion('original_webp') ? $m->getUrl('original_webp') : $m->getUrl(),
                ],
                'blurhash' => $m->getCustomProperty('blurhash'),
                'width' => $m->getCustomProperty('width'),
                'height' => $m->getCustomProperty('height'),
                'order' => $m->order_column ?? 0,
                'size_bytes' => $m->size,
            ];
        }

        return $rows;
    }
}
