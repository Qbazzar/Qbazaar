<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Models\HomeSectionItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * One home section as the admin screen shows it: its mode, its hand-picked
 * ads in order (whatever their status) and how many it may hold.
 */
final readonly class HomeSectionPanel
{
    /**
     * @param Collection<int, HomeSectionItem> $items
     */
    public function __construct(
        public HomeSection $section,
        public HomeSectionMode $mode,
        public Collection $items,
        public int $limit,
    ) {}

    public function isManual(): bool
    {
        return $this->mode === HomeSectionMode::MANUAL;
    }

    public function isFull(): bool
    {
        return $this->items->count() >= $this->limit;
    }

    /**
     * Picks the public home will actually show: listed ads only.
     */
    public function liveCount(): int
    {
        return $this->items->filter(static fn (HomeSectionItem $item): bool => $item->ad?->isPubliclyListed() ?? false)->count();
    }
}
