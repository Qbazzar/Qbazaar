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
use Illuminate\Support\Facades\Log;

/**
 * Owns the OTP lifecycle (issue / verify / expire / count) so controllers and
 * actions stay thin and the OTP rules live in one place.
 *
 * Storage rules:
 *  - The recipient is a phone or an email address, depending on the purpose.
 *  - The 6-digit code is hashed at rest via Hash::make — only the raw value
 *    handed to the delivery channel ever leaves PHP memory.
 *  - Codes are scoped by purpose, so a code issued for one flow can never
 *    satisfy another. Each purpose has its own expiry and attempt budget.
 *  - At most one ACTIVE row per recipient and purpose: issuing a new code
 *    soft-burns any prior `used_at IS NULL` row.
 *  - `attempts >= max_attempts` → row gets soft-burned and the next attempt
 *    is treated as AUTH_005 (caller must resend).
 */
class OtpService
{
    /**
     * Issue a fresh code. Returns the **raw** code so it can be handed to the
     * delivery channel; the DB only stores the salted hash.
     */
    public function issue(string $recipient, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): OtpIssueResult
    {
        $length = (int) config('qbazaar.otp.length', 6);
        $ttlMinutes = $purpose->ttlMinutes();
        $cooldownSeconds = (int) config('qbazaar.otp.resend_cooldown_seconds', 60);

        $fixedCode = $this->fixedCode();
        $raw = $fixedCode !== null
            ? str_pad($fixedCode, $length, '0', STR_PAD_LEFT)
            : $this->generateNumeric($length);

        DB::transaction(function () use ($recipient, $purpose, $raw, $ttlMinutes): void {
            $this->expireAllFor($recipient, $purpose);

            OtpCode::query()->create([
                'recipient' => $recipient,
                'purpose' => $purpose,
                'code_hash' => Hash::make($raw),
                'attempts' => 0,
                'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
                'used_at' => null,
            ]);
        });

        return new OtpIssueResult(
            recipient: $recipient,
            rawCode: $raw,
            expiresIn: $ttlMinutes * 60,
            canResendIn: $cooldownSeconds,
        );
    }

    /**
     * Verify a presented code against the active code for the recipient.
     *
     *  - Active row missing → AUTH_005 (expired vs never-sent stays ambiguous
     *    to avoid enumeration).
     *  - Row expired → AUTH_004.
     *  - Wrong code → attempts + 1 (row burnt once the budget is spent), AUTH_005.
     *  - Right code → consumed, unless $consume is false (the caller only
     *    needs to know the code is right and will come back with it).
     *
     * The row is locked so two concurrent requests cannot both spend the same
     * code or race past the attempt budget.
     *
     * @throws DomainException
     */
    public function verify(
        string $recipient,
        string $code,
        OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION,
        bool $consume = true,
    ): void {
        $failure = DB::transaction(function () use ($recipient, $code, $purpose, $consume): ?ErrorCode {
            /** @var OtpCode|null $row */
            $row = $this->queryFor($recipient, $purpose)
                ->whereNull('used_at')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return ErrorCode::AUTH_OTP_INVALID;
            }

            if ($row->isExpired()) {
                return ErrorCode::AUTH_OTP_EXPIRED;
            }

            if (Hash::check($code, $row->code_hash)) {
                if ($consume) {
                    $row->forceFill(['used_at' => Carbon::now()])->save();
                }

                return null;
            }

            $attempts = $row->attempts + 1;

            $row->forceFill([
                'attempts' => $attempts,
                'used_at' => $attempts >= $purpose->maxAttempts() ? Carbon::now() : null,
            ])->save();

            return ErrorCode::AUTH_OTP_INVALID;
        });

        if ($failure !== null) {
            throw new DomainException($failure);
        }
    }

    /**
     * The currently-active code row for a recipient (the one a verify call
     * would test against). Returns `null` if none is in flight.
     */
    public function activeRowFor(string $recipient, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): ?OtpCode
    {
        /** @var OtpCode|null $row */
        $row = $this->queryFor($recipient, $purpose)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        return $row;
    }

    /**
     * Soft-burn every active code for the recipient.
     */
    public function expireAllFor(string $recipient, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): void
    {
        $this->queryFor($recipient, $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => Carbon::now()]);
    }

    /**
     * How many codes have been issued for this recipient within the rolling
     * hour — used by the hourly send ceiling.
     */
    public function countLastHour(string $recipient, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): int
    {
        return $this->queryFor($recipient, $purpose)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();
    }

    /**
     * @return Builder<OtpCode>
     */
    private function queryFor(string $recipient, OtpPurpose $purpose): Builder
    {
        return OtpCode::query()
            ->where('recipient', $recipient)
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

    /**
     * Dev override (OTP_FIXED_CODE): every issued code gets this value while
     * the rest of the flow (hash at rest, attempts, expiry, delivery) runs
     * unchanged. Production ignores it, since a known code would let anyone
     * verify any phone or email.
     */
    private function fixedCode(): ?string
    {
        $fixedCode = config('qbazaar.otp.fixed_code');

        if (! is_string($fixedCode) || $fixedCode === '') {
            return null;
        }

        if (app()->isProduction()) {
            Log::critical('OTP_FIXED_CODE is set in production and was ignored; unset it.');

            return null;
        }

        return $fixedCode;
    }
}
