<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;
use App\Notifications\Account\ReauthCodeNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Asks for a step-up code through the real endpoint and reads it back from
 * the faked notification. Needs Notification::fake() and $user signed in.
 */
trait RequestsReauthCodes
{
    protected function requestReauthCode(User $user): string
    {
        $this->postJson('/api/v1/account/reauth-code')->assertStatus(202);

        $code = null;
        Notification::assertSentTo($user, ReauthCodeNotification::class, function (ReauthCodeNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        return (string) $code;
    }

    protected function wrongReauthCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }
}
