<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Display form of a stored phone number. Qatari numbers read as
 * "+974 3900 0000"; anything else is shown as stored.
 */
final class PhoneFormat
{
    public static function display(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (preg_match('/^(?:00)?974(\d{4})(\d{4})$/', $digits, $parts) === 1) {
            return "+974 {$parts[1]} {$parts[2]}";
        }

        return $phone;
    }
}
