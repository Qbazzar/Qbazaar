<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Decides which tiles of a dashboard payload become headline KPI cards, which
 * become share bars, and which are left for the "more figures" list. Pure
 * presentation choices, kept out of the Blade files.
 */
final class DashboardLayout
{
    private const int MAX_KPIS = 6;

    /** Tile => the delta tile and daily series that go with it. */
    private const array KPIS = [
        ['tile' => 'users_total', 'delta' => 'users_new', 'series' => 'users', 'tone' => 'blue', 'icon' => 'users'],
        ['tile' => 'ads_active', 'delta' => 'ads_new', 'series' => 'ads', 'tone' => 'coral', 'icon' => 'tag'],
        ['tile' => 'orders_total', 'series' => 'orders', 'tone' => 'violet', 'icon' => 'banknotes'],
        ['tile' => 'orders_gmv', 'series' => 'revenue', 'field' => 'gmv', 'tone' => 'violet', 'icon' => 'banknotes'],
        ['tile' => 'commission_earned', 'series' => 'revenue', 'field' => 'commission', 'tone' => 'aqua', 'icon' => 'banknotes'],
        ['tile' => 'messages_new', 'series' => 'messages', 'tone' => 'slate', 'icon' => 'chat'],
        ['tile' => 'reports_new', 'tone' => 'slate', 'icon' => 'flag'],
        ['tile' => 'tickets_new', 'tone' => 'slate', 'icon' => 'lifebuoy'],
    ];

    /** Stock figures shown as a share of `of` (ads, users, or a percentage). */
    private const array STOCK = [
        ['tile' => 'ads_pending', 'of' => 'ads', 'tone' => 'warning', 'icon' => 'tag'],
        ['tile' => 'ads_expired', 'of' => 'ads', 'tone' => 'muted', 'icon' => 'tag'],
        ['tile' => 'ads_rejected', 'of' => 'ads', 'tone' => 'danger', 'icon' => 'tag'],
        ['tile' => 'users_verified', 'of' => 'percent', 'tone' => 'success', 'icon' => 'users'],
        ['tile' => 'users_active', 'of' => 'users', 'tone' => 'blue', 'icon' => 'users'],
        ['tile' => 'users_business', 'of' => 'users', 'tone' => 'violet', 'icon' => 'users'],
    ];

    /**
     * Engagement trends (AD-17.6), each on its own axis because messages
     * outnumber new chats and follows by an order of magnitude.
     */
    private const array ENGAGEMENT = [
        ['series' => 'messages', 'tile' => 'messages_new', 'tone' => 'slate'],
        ['series' => 'conversations', 'tile' => 'conversations_new', 'tone' => 'aqua'],
        ['series' => 'follows', 'tile' => 'follows_new', 'tone' => 'violet'],
    ];

    /** @var Collection<string, array<string, mixed>> */
    private readonly Collection $tiles;

    /**
     * @param array<string, mixed> $stats
     */
    public function __construct(private readonly array $stats)
    {
        $tiles = [];
        foreach ($stats['groups'] as $group) {
            foreach ($group['tiles'] as $tile) {
                $tiles[$tile['key']] = $tile;
            }
        }
        $this->tiles = new Collection($tiles);
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function tiles(): Collection
    {
        return $this->tiles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function kpis(): array
    {
        $available = array_filter(self::KPIS, fn (array $def): bool => $this->tiles->has($def['tile']));

        return array_slice(array_values($available), 0, self::MAX_KPIS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stock(): array
    {
        return array_values(array_filter(self::STOCK, fn (array $def): bool => $this->tiles->has($def['tile'])));
    }

    /**
     * What each stock figure is a share of.
     *
     * @return array{ads: int|float, users: int|float, percent: int}
     */
    public function totals(): array
    {
        return [
            'ads' => array_sum($this->stats['distributions']['ads'] ?? []),
            'users' => $this->tiles->get('users_total')['value'] ?? 0,
            'percent' => 100,
        ];
    }

    /**
     * Tiles with no card of their own.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function moreTiles(): Collection
    {
        $shown = [];
        foreach ([...$this->kpis(), ...$this->stock()] as $def) {
            array_push($shown, $def['tile'], ...(isset($def['delta']) ? [$def['delta']] : []));
        }

        return $this->tiles->reject(static fn (array $tile, string $key): bool => in_array($key, $shown, true));
    }

    /**
     * One small chart per engagement series the staff member may see, with
     * the period's figure from the matching tile.
     *
     * @return list<array{key: string, label: string, value: string|null, change: int|null|false, series: list<array<string, mixed>>}>
     */
    public function engagementCharts(): array
    {
        $charts = [];

        foreach (self::ENGAGEMENT as $def) {
            $points = $this->stats['series'][$def['series']] ?? null;
            if ($points === null) {
                continue;
            }

            $label = (string) __('admin.home.engagement.' . $def['series']);
            $tile = $this->tiles->get($def['tile']);
            $charts[] = [
                'key' => $def['series'],
                'label' => $label,
                'value' => $tile !== null ? $this->format($tile) : null,
                'change' => $tile !== null && array_key_exists('change', $tile) ? $tile['change'] : false,
                'series' => [['label' => $label, 'tone' => $def['tone'], 'field' => 'count', 'points' => $points]],
            ];
        }

        return $charts;
    }

    /**
     * @param array<string, mixed> $tile
     */
    public function format(array $tile): string
    {
        return match ($tile['kind']) {
            'money' => Money::format((string) $tile['value']),
            'percent' => $tile['value'] . '%',
            default => number_format($tile['value']),
        };
    }
}
