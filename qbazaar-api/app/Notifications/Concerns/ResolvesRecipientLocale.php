<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\Language;
use App\Models\User;

/**
 * The language a notification is written in: the recipient's own when they
 * chose one, otherwise the platform default.
 */
trait ResolvesRecipientLocale
{
    protected function recipientLocale(mixed $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->language instanceof Language) {
            return $notifiable->language->value;
        }

        return (string) config('qbazaar.default_language', 'ar');
    }
}
