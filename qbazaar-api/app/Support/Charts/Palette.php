<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * The dashboard's chart colour roles. The values live once, as `--viz-*`
 * custom properties in resources/css/app.css (mapped to the admin theme
 * tokens); this only names them.
 */
final class Palette
{
    private const array TONES = ['blue', 'coral', 'violet', 'aqua', 'slate', 'success', 'warning', 'danger', 'info', 'muted'];

    public static function css(string $tone): string
    {
        return 'var(--viz-' . (in_array($tone, self::TONES, true) ? $tone : 'slate') . ')';
    }
}
