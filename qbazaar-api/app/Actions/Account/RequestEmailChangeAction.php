<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use App\Notifications\Account\ConfirmEmailChangeNotification;
use App\Services\Account\ReauthCodeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Starts an email change: after the step-up code, emails a confirmation
 * link to the new address. Nothing changes until that link is opened.
 *
 * The link carries a hash of the current email, so it stops working once
 * the email changes (single use) and an older link cannot undo a newer change.
 */
class RequestEmailChangeAction
{
    public function __construct(private readonly ReauthCodeService $reauth) {}

    public function execute(User $user, string $newEmail, string $reauthCode): void
    {
        $this->reauth->consume($user, $reauthCode);

        $minutes = (int) config('qbazaar.account.email_change_link_ttl_minutes');

        $url = URL::temporarySignedRoute('api.v1.account.email.confirm', Carbon::now()->addMinutes($minutes), [
            'user' => $user->id,
            'email' => $newEmail,
            'from' => self::fingerprint($user->email),
        ]);

        Notification::route('mail', $newEmail)
            ->notify((new ConfirmEmailChangeNotification($url, $minutes))->locale($user->language->value));
    }

    public static function fingerprint(string $email): string
    {
        return hash('sha256', mb_strtolower($email));
    }
}
