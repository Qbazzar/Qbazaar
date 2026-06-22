<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Account\PrivacySettings;
use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\UserStatus;
use App\Models\Pivot\UserBlock;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
 * @property string $password
 * @property AccountType $account_type
 * @property UserStatus $status
 * @property bool $email_verified
 * @property bool $phone_verified
 * @property numeric-string $rating_avg
 * @property int $rating_count
 * @property Language $language
 * @property string|null $avatar_url
 * @property PrivacySettings|null $privacy_settings
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable implements CanResetPasswordContract, FilamentUser, HasMedia, HasName, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUlids, InteractsWithMedia, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'account_type',
        'status',
        'email_verified',
        'phone_verified',
        'language',
        'avatar_url',
        'privacy_settings',
        'last_login_at',
        'deletion_requested_at',
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
     *  Filament admin panel access (Sprint 11).
     *
     *  Anyone reaching `/admin` must hold at least one of the three staff
     *  roles seeded by RolesAndPermissionsSeeder. We deliberately do NOT
     *  check `$panel->getId()` here — QBazaar only ships one panel and the
     *  next one (analytics, partner dashboard) will get its own model contract.
     * ──────────────────────────────────────────────────────────────────*/
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['super_admin', 'moderator', 'support']);
    }

    /**
     * Display name Filament reads for the avatar dropdown header. Our User
     * model uses `full_name` instead of the framework default `name`, so
     * without this override Filament's `FilamentManager::getUserName()`
     * tries to read `$user->name`, gets null, and crashes with a
     * "Return value must be of type string, null returned" type error.
     */
    public function getFilamentName(): string
    {
        return $this->full_name;
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
        $this->addMediaCollection('avatar')->singleFile();
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
