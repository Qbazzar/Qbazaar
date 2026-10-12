<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * The dashboard's chart colours by role. Series hues come from the
 * validated categorical order (blue, coral, violet, aqua) with the brand
 * coral for ads; status hues are the admin's semantic colours. Each is a CSS
 * custom property with its hex as the fallback, so a theme can override it.
 */
final class Palette
{
    private const array TONES = [
        'blue' => '#2a78d6',
        'coral' => '#f37335',
        'violet' => '#6250d6',
        'aqua' => '#1baf7a',
        'slate' => '#52514e',
        'success' => '#059669',
        'warning' => '#d97706',
        'danger' => '#dc2626',
        'info' => '#0284c7',
        'muted' => '#9ca3af',
    ];

    public static function css(string $tone): string
    {
        $hex = self::TONES[$tone] ?? self::TONES['slate'];

        return "var(--viz-{$tone}, {$hex})";
    }
}
