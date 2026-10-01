<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\Account\EmailChangedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Applies an email change from the signed link sent to the new address,
 * then tells the old address.
 */
class ConfirmEmailChangeAction
{
    public function execute(string $userId, string $newEmail, string $fromFingerprint): User
    {
        try {
            [$user, $oldEmail] = DB::transaction(function () use ($userId, $newEmail, $fromFingerprint): array {
                $user = User::query()->lockForUpdate()->find($userId);

                if ($user === null || ! hash_equals(RequestEmailChangeAction::fingerprint($user->email), $fromFingerprint)) {
                    throw new DomainException(ErrorCode::ACCOUNT_EMAIL_CHANGE_LINK_INVALID);
                }

                if (User::query()->withTrashed()->where('email', $newEmail)->exists()) {
                    throw new DomainException(ErrorCode::AUTH_EMAIL_EXISTS);
                }

                $oldEmail = $user->email;
                $user->forceFill(['email' => $newEmail, 'email_verified' => true])->save();

                return [$user, $oldEmail];
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::AUTH_EMAIL_EXISTS);
        }

        Notification::route('mail', $oldEmail)
            ->notify((new EmailChangedNotification($this->mask($newEmail)))->locale($user->language->value));

        return $user;
    }

    private function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($local, 0, 2) . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain;
    }
}
