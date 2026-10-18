<?php

declare(strict_types=1);

namespace App\Services\Admin\Inbox;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One row of the staff inbox, read from the stored payload (`title`, `body`,
 * `icon`, `cta_url`, `category`) so the view only renders.
 */
final readonly class InboxEntry
{
    /** Payload icons the admin icon set can draw. */
    private const array ICONS = ['flag', 'inbox', 'lifebuoy', 'banknotes', 'shield', 'bell', 'exclamation', 'tag', 'chat', 'users'];

    /** Fallback icon by the category's first segment. */
    private const array CATEGORY_ICONS = [
        'ad' => 'tag',
        'report' => 'flag',
        'support' => 'lifebuoy',
        'finance' => 'banknotes',
        'ledger' => 'exclamation',
        'order' => 'banknotes',
    ];

    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public string $icon,
        public CarbonImmutable $at,
        public bool $unread,
        public bool $hasLink,
    ) {}

    public static function from(DatabaseNotification $notification): self
    {
        $data = (array) $notification->data;
        $category = (string) ($notification->getAttribute('category') ?? $data['category'] ?? '');

        return new self(
            id: (string) $notification->getKey(),
            title: self::text($data['title'] ?? null) ?: (string) __('admin.inbox.bell'),
            body: self::text($data['body'] ?? null),
            icon: self::iconFor($data['icon'] ?? null, $category),
            at: CarbonImmutable::instance($notification->created_at ?? now()),
            unread: $notification->read_at === null,
            hasLink: AdminTarget::from(self::text($data['cta_url'] ?? null) ?: null) !== null,
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function iconFor(mixed $icon, string $category): string
    {
        if (is_string($icon) && in_array($icon, self::ICONS, true)) {
            return $icon;
        }

        return self::CATEGORY_ICONS[strstr($category, '.', true) ?: $category] ?? 'bell';
    }
}
