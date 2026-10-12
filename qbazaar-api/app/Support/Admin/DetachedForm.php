<?php

declare(strict_types=1);

namespace App\Support\Admin;

/**
 * Names the hidden form behind an action button that sits inside another form
 * (see x-admin.detached-form). The id is derived from what the form submits, so
 * the same action rendered twice on a page shares one form.
 */
final class DetachedForm
{
    /**
     * @param array<string, scalar|null> $fields
     */
    public static function id(string $action, string $method = 'POST', array $fields = []): string
    {
        return 'qb-action-' . substr(sha1(strtoupper($method) . ' ' . $action . ' ' . json_encode($fields)), 0, 12);
    }
}
