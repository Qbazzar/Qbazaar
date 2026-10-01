<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Account\PrivacySettings;
use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\StaffRole;
use App\Enums\UserStatus;
use App\Models\Pivot\UserBlock;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $full_name
 * @property string $email
 * @property string $phone
 * @property string|null $password
 * @property AccountType $account_type
 * @property UserStatus $status
 * @property bool $email_verified
 * @property bool $phone_verified
 * @property numeric-string $rating_avg
 * @property int $rating_count
 * @property int $followers_count
 * @property int $following_count
 * @property BusinessProfile|null $businessProfile
 * @property Language $language
 * @property string|null $avatar_url
 * @property PrivacySettings|null $privacy_settings
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable implements CanResetPasswordContract, HasMedia, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUlids, InteractsWithMedia, Notifiable, SoftDeletes;

    public const BUSINESS_COVER_COLLECTION = 'business_cover';

    /**
     * Status, verification flags and lifecycle timestamps are left out on
     * purpose: only the actions that own them may set them, via forceFill.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'account_type',
        'language',
        'avatar_url',
        'privacy_settings',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'last_login_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'account_type' => AccountType::class,
            'status' => UserStatus::class,
            'language' => Language::class,
            'privacy_settings' => PrivacySettings::class,
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'followers_count' => 'integer',
            'following_count' => 'integer',
        ];
    }

    /**
     * Reviews this user received as a seller.
     *
     * @return HasMany<Review, $this>
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'seller_id');
    }

    /**
     * Resolve the privacy settings DTO, returning sensible defaults when the
     * column is null (legacy rows). Centralising the fallback here keeps every
     * caller free of repeated null-coalescing.
     */
    public function privacySettings(): PrivacySettings
    {
        return $this->privacy_settings ?? PrivacySettings::defaults();
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(StaffRole::names());
    }

    public function isBusiness(): bool
    {
        return $this->account_type === AccountType::BUSINESS;
    }

    /** @return HasOne<BusinessProfile, $this> */
    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Ads — a user's listings across every status.
     * ──────────────────────────────────────────────────────────────────*/

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Device tokens — FCM registration tokens, one row per browser/device.
     *  `routeNotificationForFcm()` is the hook the FCM notification channel
     *  calls to resolve where a push should go.
     * ──────────────────────────────────────────────────────────────────*/

    /** @return HasMany<DeviceToken, $this> */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** @return list<string> FCM registration tokens for this user's devices */
    public function routeNotificationForFcm(): array
    {
        $tokens = $this->deviceTokens()
            ->pluck('token')
            ->filter(static fn (mixed $token): bool => is_string($token) && $token !== '')
            ->all();

        return array_values($tokens);
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Blocked users — many-to-many via `user_blocks` pivot.
     * ──────────────────────────────────────────────────────────────────*/

    /** @return BelongsToMany<User, $this, UserBlock, 'pivot'> */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_blocks',
            'blocker_id',
            'blocked_id',
        )
            ->withPivot('created_at')
            ->using(UserBlock::class);
    }

    /** @return BelongsToMany<User, $this, UserBlock, 'pivot'> */
    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_blocks',
            'blocked_id',
            'blocker_id',
        )
            ->withPivot('created_at')
            ->using(UserBlock::class);
    }

    public function hasBlocked(User $other): bool
    {
        return $this->blockedUsers()->where('blocked_id', $other->id)->exists();
    }

    /**
     * Accounts created through an email code or Google / Apple have no
     * password until the owner sets one through the reset flow.
     */
    public function passwordMatches(string $plain): bool
    {
        return $this->password !== null && Hash::check($plain, $this->password);
    }

    /**
     * Whether either user has blocked the other; both lookups hit the pivot's
     * primary key. With $lock, inside a transaction, a concurrent block of the
     * pair waits until that transaction ends. first() rather than exists()
     * keeps the lock on the outer select, where MySQL applies it.
     */
    public function isBlockedEitherWay(User $other, bool $lock = false): bool
    {
        return DB::table('user_blocks')
            ->where(fn ($query) => $query->where('blocker_id', $this->id)->where('blocked_id', $other->id))
            ->orWhere(fn ($query) => $query->where('blocker_id', $other->id)->where('blocked_id', $this->id))
            ->when($lock, fn ($query) => $query->sharedLock())
            ->first(['blocker_id']) !== null;
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Follows — the counts are denormalised on users by FollowGraph.
     * ──────────────────────────────────────────────────────────────────*/

    /** @return HasMany<Follow, $this> */
    public function followings(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /** @return HasMany<Follow, $this> */
    public function followerLinks(): HasMany
    {
        return $this->hasMany(Follow::class, 'followed_id');
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Password reset — wire Laravel's Password broker to our localised
     *  notification instead of the default English one.
     * ──────────────────────────────────────────────────────────────────*/
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswordResetNotification($token));
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Email verification — we use a `email_verified` boolean (legacy
     *  reason: easier to query in the admin); these methods translate
     *  Laravel's MustVerifyEmail contract onto our boolean.
     * ──────────────────────────────────────────────────────────────────*/
    public function hasVerifiedEmail(): bool
    {
        return (bool) $this->email_verified;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified' => true])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationNotification);
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Media library — avatar collection.
     *
     *  Why `singleFile()`? Avatars are a 1:1 — uploading a new one should
     *  replace the previous file, not stack alongside it.
     *  Why two conversions? Lists/cards need a tiny square thumb; profile
     *  headers need a larger square. Both keep the original aspect-square
     *  to avoid awkward crops on circular masks.
     * ──────────────────────────────────────────────────────────────────*/
    public function registerMediaCollections(): void
    {
        // Avatars are public profile pictures, so the original may be linked
        // permanently and lives on the public disk with its conversions.
        $this->addMediaCollection('avatar')
            ->useDisk((string) config('qbazaar.uploads.public_disk'))
            ->singleFile();

        $this->addMediaCollection(self::BUSINESS_COVER_COLLECTION)
            ->useDisk((string) config('qbazaar.uploads.public_disk'))
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->performOnCollections('avatar')
            ->fit(Fit::Crop, 200, 200);

        $this->addMediaConversion('medium')
            ->nonQueued()
            ->performOnCollections('avatar')
            ->fit(Fit::Crop, 480, 480);

        $this->addMediaConversion('cover')
            ->queued()
            ->performOnCollections(self::BUSINESS_COVER_COLLECTION)
            ->fit(Fit::Crop, 1200, 400);
    }

    /**
     * Business cover banner; the original is served until the queued crop exists.
     * Reads the loaded media relation when the caller eager-loaded it.
     */
    public function businessCoverUrl(): ?string
    {
        $media = $this->getFirstMedia(self::BUSINESS_COVER_COLLECTION);

        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion('cover') ? $media->getUrl('cover') : $media->getUrl();
    }

    /**
     * Convenience accessors for the avatar URLs. Each returns null when no
     * avatar exists; we don't want the empty-string Spatie default leaking
     * into JSON payloads — null is more honest for the frontend.
     */
    public function avatarOriginalUrl(): ?string
    {
        $media = $this->getFirstMedia('avatar');

        return $media?->getUrl() ?: null;
    }

    public function avatarThumbUrl(): ?string
    {
        $media = $this->getFirstMedia('avatar');

        return $media?->getUrl('thumb') ?: null;
    }

    public function avatarMediumUrl(): ?string
    {
        $media = $this->getFirstMedia('avatar');

        return $media?->getUrl('medium') ?: null;
    }
}
