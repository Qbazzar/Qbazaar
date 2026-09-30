<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Owns the OTP lifecycle (issue / verify / expire / count) so controllers and
 * actions stay thin and the OTP rules live in one place.
 *
 * Storage rules:
 *  - The 6-digit code is hashed at rest via Hash::make — only the raw value
 *    handed to the SMS channel ever leaves PHP memory.
 *  - Codes are scoped by purpose, so a code issued for one flow can never
 *    satisfy another.
 *  - At most one ACTIVE row per phone and purpose: issuing a new OTP
 *    soft-burns any prior `used_at IS NULL` row so a previously-sent code
 *    can't still be verified after `send-otp` is called again.
 *  - `attempts >= max_attempts` → row gets soft-burned and the next attempt
 *    is treated as AUTH_005 (caller must resend).
 *
 * Throttling rules (Cache locks are enforced in the resend-otp action — we
 * just expose `countLastHour()` so it can be checked cheaply):
 *  - 60s cooldown between sends (resend lock — caller-owned).
 *  - 5 sends per rolling hour per phone.
 */
class OtpService
{
    /**
     * Issue a fresh OTP for the given phone. Returns the **raw** code so it
     * can be handed to the SMS channel; the DB only stores the salted hash.
     */
    public function issue(string $phone, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): OtpIssueResult
    {
        $length = (int) config('qbazaar.otp.length', 6);
        $ttlMinutes = (int) config('qbazaar.otp.ttl_minutes', 5);
        $cooldownSeconds = (int) config('qbazaar.otp.resend_cooldown_seconds', 60);

        // Dev override (OTP_FIXED_CODE in .env) — when set, every issued OTP is
        // this exact value. The rest of the flow (hash at rest, attempts,
        // expiry, SMS/log delivery) runs unchanged so devs can exercise
        // the real pipeline without needing a phone in hand. Must be null in
        // production.
        $fixedCode = config('qbazaar.otp.fixed_code');
        $raw = is_string($fixedCode) && $fixedCode !== ''
            ? str_pad($fixedCode, $length, '0', STR_PAD_LEFT)
            : $this->generateNumeric($length);

        DB::transaction(function () use ($phone, $purpose, $raw, $ttlMinutes): void {
            $this->expireAllFor($phone, $purpose);

            OtpCode::query()->create([
                'phone' => $phone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($raw),
                'attempts' => 0,
                'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
                'used_at' => null,
            ]);
        });

        return new OtpIssueResult(
            phone: $phone,
            rawCode: $raw,
            expiresIn: $ttlMinutes * 60,
            canResendIn: $cooldownSeconds,
        );
    }

    /**
     * Verify a presented code against the active OTP for the phone.
     *
     *  - Active row missing → AUTH_005 (caller can't tell expired vs never-sent;
     *    we keep the same code to avoid enumeration).
     *  - Row expired → AUTH_004 (the row exists but its window has closed).
     *  - Wrong code, but attempts left → increment attempts, throw AUTH_005.
     *  - Wrong code AND attempts now == max → burn the row, throw AUTH_005.
     *
     * @throws DomainException
     */
    public function verify(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): void
    {
        $maxAttempts = (int) config('qbazaar.otp.max_attempts', 3);

        $row = $this->activeRowForPhone($phone, $purpose);

        if ($row === null) {
            throw new DomainException(ErrorCode::AUTH_OTP_INVALID);
        }

        if ($row->isExpired()) {
            throw new DomainException(ErrorCode::AUTH_OTP_EXPIRED);
        }

        if (Hash::check($code, $row->code_hash)) {
            $row->forceFill(['used_at' => Carbon::now()])->save();

            return;
        }

        $row->forceFill(['attempts' => $row->attempts + 1])->save();

        if ($row->attempts >= $maxAttempts) {
            $row->forceFill(['used_at' => Carbon::now()])->save();
        }

        throw new DomainException(ErrorCode::AUTH_OTP_INVALID);
    }

    /**
     * The currently-active OTP row for a phone (the one a verify call would
     * test against). Returns `null` if none is in flight.
     */
    public function activeRowForPhone(string $phone, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): ?OtpCode
    {
        /** @var OtpCode|null $row */
        $row = $this->queryFor($phone, $purpose)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        return $row;
    }

    /**
     * Soft-burn every active OTP for the phone — used when we want a clean
     * slate (e.g. after a successful login flow that should invalidate any
     * dangling code).
     */
    public function expireAllFor(string $phone, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): void
    {
        $this->queryFor($phone, $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => Carbon::now()]);
    }

    /**
     * How many OTPs have been issued for this phone within the rolling
     * hour — used by the resend ceiling (5/hr by default).
     */
    public function countLastHour(string $phone, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): int
    {
        return $this->queryFor($phone, $purpose)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();
    }

    /**
     * @return Builder<OtpCode>
     */
    private function queryFor(string $phone, OtpPurpose $purpose): Builder
    {
        return OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose);
    }

    /**
     * @internal exposed for tests / admin tooling
     */
    public function generateNumeric(int $length): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
