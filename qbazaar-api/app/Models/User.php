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
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

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
 * @property Language $language
 * @property string|null $avatar_url
 * @property PrivacySettings|null $privacy_settings
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable implements CanResetPasswordContract, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

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
            'account_type' => AccountType::class,
            'status' => UserStatus::class,
            'language' => Language::class,
            'privacy_settings' => PrivacySettings::class,
        ];
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
}
