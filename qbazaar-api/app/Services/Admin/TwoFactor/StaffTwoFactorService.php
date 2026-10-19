<?php

declare(strict_types=1);

namespace App\Services\Admin\TwoFactor;

use App\Models\StaffTwoFactor;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Support\Base32;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Enrols, verifies and removes a staff member's TOTP credential.
 *
 * Recovery codes are stored as SHA-256 hashes of 80-bit random strings and
 * shown once. Every change bumps the trusted-browser version and cycles the
 * remember-me token, so nothing issued before it keeps skipping the check.
 */
class StaffTwoFactorService
{
    private const int RECOVERY_CODE_BYTES = 10;

    public function __construct(
        private readonly Totp $totp,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function credentialFor(User $user): ?StaffTwoFactor
    {
        return StaffTwoFactor::query()->find($user->id);
    }

    public function isEnabled(User $user): bool
    {
        return StaffTwoFactor::query()->whereKey($user->id)->whereNotNull('confirmed_at')->exists();
    }

    /** Whether the staff member must enrol before using the panel. */
    public function isRequired(User $user): bool
    {
        return (bool) config('qbazaar.admin.two_factor.enforce')
            && $user->hasAnyRole((array) config('qbazaar.admin.two_factor.enforced_roles'));
    }

    /**
     * Starts (or restarts) enrolment with a fresh secret. A confirmed
     * credential has to be disabled first, so it is never silently replaced.
     */
    public function begin(User $user): StaffTwoFactor
    {
        $credential = $this->credentialFor($user) ?? new StaffTwoFactor;
        $credential->user_id = $user->id;

        if ($credential->isConfirmed()) {
            throw ValidationException::withMessages(['code' => __('admin.two_factor.errors.already_enabled')]);
        }

        $credential->forceFill([
            'secret' => $this->totp->generateSecret(),
            'recovery_codes' => null,
            'last_used_step' => null,
        ])->save();

        return $credential;
    }

    /**
     * Confirms enrolment with the first code from the authenticator app.
     *
     * @return list<string> the recovery codes, in plain text for this one response
     */
    public function confirm(User $user, string $code): array
    {
        $credential = $this->credentialFor($user);

        if ($credential === null || $credential->isConfirmed()) {
            throw ValidationException::withMessages(['code' => __('admin.two_factor.errors.not_pending')]);
        }

        $step = $this->totp->verify($credential->secret, $this->normaliseCode($code), Carbon::now()->getTimestamp(), $this->window());

        if ($step === null) {
            throw ValidationException::withMessages(['code' => __('admin.two_factor.errors.invalid_code')]);
        }

        $codes = $this->newRecoveryCodes();

        $credential->forceFill([
            'confirmed_at' => Carbon::now(),
            'last_used_step' => $step,
            'recovery_codes' => $this->hashes($codes),
            'device_version' => $credential->device_version + 1,
        ])->save();

        $this->cycleRememberToken($user);
        $this->audit->record($user, 'admin.two_factor.enabled', $user);

        return $codes;
    }

    /**
     * Checks a sign-in code: a TOTP code (each time step accepted once) or,
     * when `$recovery` is set, one of the recovery codes, which is then spent.
     */
    public function verifyLogin(User $user, string $code, bool $recovery = false): bool
    {
        return $recovery ? $this->spendRecoveryCode($user, $code) : $this->acceptTotp($user, $code);
    }

    /**
     * @return list<string> the new recovery codes, in plain text for this one response
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $credential = $this->confirmedCredential($user);
        $codes = $this->newRecoveryCodes();

        $credential->forceFill(['recovery_codes' => $this->hashes($codes)])->save();

        $this->audit->record($user, 'admin.two_factor.recovery_codes_regenerated', $user);

        return $codes;
    }

    /**
     * Removes the credential. `$actor` is the staff member themselves, or a
     * senior colleague resetting a lost device.
     */
    public function disable(User $actor, User $user): void
    {
        $deleted = StaffTwoFactor::query()->whereKey($user->id)->delete();

        if ($deleted === 0) {
            return;
        }

        $this->cycleRememberToken($user);
        $this->audit->record(
            $actor,
            $actor->is($user) ? 'admin.two_factor.disabled' : 'admin.two_factor.reset',
            $user,
        );
    }

    public function provisioningUri(User $user, StaffTwoFactor $credential): string
    {
        return $this->totp->provisioningUri((string) config('qbazaar.admin.two_factor.issuer'), $user->email, $credential->secret);
    }

    private function acceptTotp(User $user, string $code): bool
    {
        $credential = $this->confirmedCredential($user, orFail: false);
        if ($credential === null) {
            return false;
        }

        $step = $this->totp->verify(
            $credential->secret,
            $this->normaliseCode($code),
            Carbon::now()->getTimestamp(),
            $this->window(),
            $credential->last_used_step,
        );

        if ($step === null) {
            return false;
        }

        // The conditional write makes two requests racing with the same code
        // see one success between them.
        return StaffTwoFactor::query()
            ->whereKey($user->id)
            ->where(fn (Builder $query) => $query->whereNull('last_used_step')->orWhere('last_used_step', '<', $step))
            ->update(['last_used_step' => $step]) === 1;
    }

    private function spendRecoveryCode(User $user, string $code): bool
    {
        $hash = $this->hash($this->normaliseRecoveryCode($code));

        $spent = DB::transaction(function () use ($user, $hash): bool {
            $credential = StaffTwoFactor::query()->whereKey($user->id)->whereNotNull('confirmed_at')->lockForUpdate()->first();
            if ($credential === null) {
                return false;
            }

            $remaining = $credential->recovery_codes ?? [];
            $index = $this->indexOf($remaining, $hash);
            if ($index === null) {
                return false;
            }

            array_splice($remaining, $index, 1);
            $credential->forceFill(['recovery_codes' => $remaining])->save();

            return true;
        });

        if ($spent) {
            $this->audit->record($user, 'admin.two_factor.recovery_code_used', $user);
        }

        return $spent;
    }

    /**
     * @param list<string> $hashes
     */
    private function indexOf(array $hashes, string $hash): ?int
    {
        foreach ($hashes as $index => $candidate) {
            if (hash_equals($candidate, $hash)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return ($orFail is true ? StaffTwoFactor : StaffTwoFactor|null)
     */
    private function confirmedCredential(User $user, bool $orFail = true): ?StaffTwoFactor
    {
        $credential = StaffTwoFactor::query()->whereKey($user->id)->whereNotNull('confirmed_at')->first();

        if ($credential === null && $orFail) {
            throw ValidationException::withMessages(['code' => __('admin.two_factor.errors.not_enabled')]);
        }

        return $credential;
    }

    /**
     * @return list<string>
     */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        $count = (int) config('qbazaar.admin.two_factor.recovery_codes');

        for ($i = 0; $i < $count; $i++) {
            $codes[] = implode('-', str_split(Base32::encode(random_bytes(self::RECOVERY_CODE_BYTES)), 4));
        }

        return $codes;
    }

    /**
     * @param list<string> $codes
     * @return list<string>
     */
    private function hashes(array $codes): array
    {
        return array_map(fn (string $code): string => $this->hash($this->normaliseRecoveryCode($code)), $codes);
    }

    private function hash(string $normalisedCode): string
    {
        return hash('sha256', $normalisedCode);
    }

    private function normaliseCode(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? '';
    }

    private function normaliseRecoveryCode(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim($code)));
    }

    private function window(): int
    {
        return (int) config('qbazaar.admin.two_factor.window');
    }

    private function cycleRememberToken(User $user): void
    {
        // A remember-me cookie minted before this change must not keep signing in.
        $token = Str::random(60);
        User::query()->whereKey($user->id)->toBase()->update(['remember_token' => $token]);
        $user->setRememberToken($token);
    }
}
