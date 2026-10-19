<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Models\Category;
use App\Models\Location;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use App\Services\Catalog\Taxonomy\TaxonomyUsage;
use Illuminate\Contracts\Session\Session;

/**
 * The notice a refused delete leaves on the page it returns to: what still
 * uses the row, and the one-click way to deactivate it instead. It travels
 * through the session flash, so it holds plain strings only.
 */
final readonly class DeleteBlockedNotice
{
    public const SESSION_KEY = 'delete_blocked';

    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public string $title,
        public array $reasons,
        public ?string $deactivateUrl,
    ) {}

    /**
     * @return array{title: string, reasons: list<string>, deactivate_url: string|null}
     */
    public static function payload(Category|Location $node, TaxonomyUsage $usage): array
    {
        $kind = TaxonomyKind::of($node);
        $name = $node->getLocalizedName(app()->getLocale());

        return [
            'title' => (string) __('admin.catalog.delete.blocked_title_' . $kind->value, ['name' => $name]),
            'reasons' => $usage->reasons($kind),
            'deactivate_url' => $node->is_active ? route('admin.' . $kind->routeName() . '.status', $node) : null,
        ];
    }

    public static function fromSession(Session $session): ?self
    {
        $payload = $session->get(self::SESSION_KEY);

        if (! is_array($payload) || ! is_string($payload['title'] ?? null)) {
            return null;
        }

        return new self(
            $payload['title'],
            array_values(array_filter((array) ($payload['reasons'] ?? []), 'is_string')),
            is_string($payload['deactivate_url'] ?? null) ? $payload['deactivate_url'] : null,
        );
    }
}
