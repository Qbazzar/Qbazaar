<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use App\Enums\OrderStatus;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Admin\Dashboard\Collectors\AdStats;
use App\Services\Admin\Dashboard\Collectors\AttentionQueues;
use App\Services\Admin\Dashboard\Collectors\Breakdowns;
use App\Services\Admin\Dashboard\Collectors\DailySeries;
use App\Services\Admin\Dashboard\Collectors\EngagementStats;
use App\Services\Admin\Dashboard\Collectors\FinanceStats;
use App\Services\Admin\Dashboard\Collectors\FollowStats;
use App\Services\Admin\Dashboard\Collectors\RecentActivity;
use App\Services\Admin\Dashboard\Collectors\SystemHealth;
use App\Services\Admin\Dashboard\Collectors\UserStats;
use App\Services\Catalog\WarmedCache;
use Carbon\CarbonImmutable;

/**
 * Everything the admin home shows, as plain arrays so it can sit in the cache.
 *
 * What a staff member sees depends on their permissions ({@see DashboardAccess}),
 * so the payload is cached per period and per permission set (the "role").
 * One request rebuilds a cold entry while the others wait (WarmedCache), and
 * every figure comes from a grouped aggregate, never a query per metric row.
 * System health is probed separately and refreshed more often.
 */
class DashboardStatsService
{
    public const int TTL_SECONDS = 300;

    public const int HEALTH_TTL_SECONDS = 60;

    // v3: follow and conversation series and the most-followed list (AD-17.6).
    private const string KEY_PREFIX = 'admin:dashboard:v3:';

    public function __construct(
        private readonly WarmedCache $cache,
        private readonly UserStats $users,
        private readonly AdStats $ads,
        private readonly FinanceStats $finance,
        private readonly EngagementStats $engagement,
        private readonly AttentionQueues $queues,
        private readonly DailySeries $series,
        private readonly Breakdowns $breakdowns,
        private readonly RecentActivity $recent,
        private readonly SystemHealth $health,
        private readonly FollowStats $follows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, DashboardPeriod $period): array
    {
        $sections = DashboardAccess::sectionsFor($user);
        $key = self::KEY_PREFIX . $period->value . ':' . md5(implode(',', $sections));

        $payload = $this->cache->get($key, self::TTL_SECONDS, fn (): array => $this->build($sections, $period));

        if (in_array(DashboardAccess::SYSTEM, $sections, true)) {
            $payload['system'] = $this->cache->get(
                self::KEY_PREFIX . 'system',
                self::HEALTH_TTL_SECONDS,
                fn (): array => $this->health->check(CarbonImmutable::now()),
            );
        }

        if (isset($payload['system'])) {
            // From the 60-second health probe, not the 5-minute payload, so the
            // attention list and the health card never disagree.
            $payload['attention'][] = ['key' => 'failed_jobs', 'count' => $payload['system']['failed_jobs'], 'oldest' => null, 'amount' => null, 'href' => null];
        }
        $payload['attention'] = AttentionPriority::sort($payload['attention'], CarbonImmutable::now());

        return $payload + ['sections' => $sections, 'period' => $period->value];
    }

    /**
     * @param list<string> $sections
     * @return array<string, mixed>
     */
    private function build(array $sections, DashboardPeriod $period): array
    {
        $now = DashboardPeriod::now();
        $window = $period->window($now);
        $has = static fn (string $section): bool => in_array($section, $sections, true);
        $ads = $has(DashboardAccess::CONTENT) || $has(DashboardAccess::REVIEW) ? $this->ads->collect($window) : null;
        $groups = $this->groups($window, $sections, $ads);

        return [
            'generated_at' => $now->toIso8601String(),
            'groups' => $groups,
            'attention' => $this->attention($has, $ads['pending'] ?? null),
            'series' => $this->seriesFor($now, $has),
            'distributions' => $this->distributions($ads, $groups, $has),
            'breakdowns' => $has(DashboardAccess::CONTENT)
                ? ['categories' => $this->breakdowns->categories(), 'locations' => $this->breakdowns->locations()]
                : [],
            'recent' => $this->recentFor($has),
        ];
    }

    /**
     * @param list<string> $sections
     * @param array{tiles: list<array<string, mixed>>, pending: array<string, mixed>}|null $ads
     * @return list<array{key: string, icon: string, tiles: list<array<string, mixed>>}>
     */
    private function groups(DashboardWindow $window, array $sections, ?array $ads): array
    {
        $has = static fn (string $section): bool => in_array($section, $sections, true);
        $completed = $has(DashboardAccess::FINANCE) ? $this->finance->completedTotals($window) : null;

        $groups = [
            'users' => $has(DashboardAccess::USERS) ? $this->users->tiles($window) : [],
            'ads' => $ads !== null && $has(DashboardAccess::CONTENT) ? $ads['tiles'] : [],
            'orders' => $completed ? $this->finance->orderTiles($window, $completed) : [],
            'money' => $completed ? $this->finance->commissionTiles($completed) : [],
            'promotions' => $has(DashboardAccess::FINANCE) ? $this->finance->promotionTiles($window) : [],
            'engagement' => $this->engagement->tiles($window, $sections),
            'support' => $this->supportTiles($window, $has),
        ];

        $icons = ['users' => 'users', 'ads' => 'tag', 'orders' => 'banknotes', 'money' => 'banknotes', 'promotions' => 'star', 'engagement' => 'chat', 'support' => 'lifebuoy'];
        $built = [];
        foreach (array_filter($groups) as $key => $tiles) {
            $built[] = ['key' => $key, 'icon' => $icons[$key], 'tiles' => $tiles];
        }

        return $built;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function supportTiles(DashboardWindow $window, callable $has): array
    {
        $tiles = [];
        if ($has(DashboardAccess::MODERATION)) {
            $tiles[] = ['key' => 'reports_new', 'kind' => 'count', 'href' => route('admin.reports.index', [], false)] + $window->trend(Report::query());
        }
        if ($has(DashboardAccess::SUPPORT)) {
            $tiles[] = ['key' => 'tickets_new', 'kind' => 'count', 'href' => route('admin.support.index', [], false)] + $window->trend(SupportTicket::query());
        }

        return $tiles;
    }

    /**
     * The queues staff clear, each tagged with the admin page that handles it.
     *
     * @param array<string, mixed>|null $pending
     * @return list<array<string, mixed>>
     */
    private function attention(callable $has, ?array $pending): array
    {
        $items = [];

        if ($has(DashboardAccess::REVIEW)) {
            $items[] = ['key' => 'pending_ads', 'amount' => null, 'href' => route('admin.moderation.queue', [], false)] + $pending;
        }
        $sources = [
            [DashboardAccess::MODERATION, 'open_reports', fn (): array => $this->queues->reports()],
            [DashboardAccess::SUPPORT, 'open_tickets', fn (): array => $this->queues->tickets()],
            [DashboardAccess::FINANCE_QUEUES, 'pending_settlements', fn (): array => $this->queues->settlements()],
            [DashboardAccess::FINANCE_QUEUES, 'pending_withdrawals', fn (): array => $this->queues->withdrawals()],
            [DashboardAccess::FINANCE_QUEUES, 'disputes', fn (): array => $this->queues->disputes()],
        ];
        foreach ($sources as [$section, $key, $load]) {
            if ($has($section)) {
                $items[] = ['key' => $key] + $load();
            }
        }

        return $items;
    }

    /**
     * Daily points for the sparklines and the trend charts; `revenue` carries
     * money as decimal strings and exists for finance viewers only.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function seriesFor(CarbonImmutable $now, callable $has): array
    {
        return array_filter([
            'users' => $has(DashboardAccess::USERS) ? $this->series->users($now) : [],
            'ads' => $has(DashboardAccess::CONTENT) ? $this->series->ads($now) : [],
            'orders' => $has(DashboardAccess::FINANCE) ? $this->series->orders($now) : [],
            'messages' => $has(DashboardAccess::CONVERSATIONS) ? $this->series->messages($now) : [],
            'conversations' => $has(DashboardAccess::CONVERSATIONS) ? $this->series->conversations($now) : [],
            'follows' => $has(DashboardAccess::USERS) ? $this->series->follows($now) : [],
            'revenue' => $has(DashboardAccess::FINANCE) ? $this->series->revenue($now) : [],
        ]);
    }

    /**
     * Status splits for the donuts. Orders reuse the order tiles already
     * built (finance only), so this adds no query.
     *
     * @param array{distribution: array<string, int>}|null $ads
     * @param list<array{key: string, tiles: list<array<string, mixed>>}> $groups
     * @return array<string, array<string, int>>
     */
    private function distributions(?array $ads, array $groups, callable $has): array
    {
        $orders = [];
        foreach (collect($groups)->firstWhere('key', 'orders')['tiles'] ?? [] as $tile) {
            if (str_starts_with($tile['key'], 'orders_') && OrderStatus::tryFrom(substr($tile['key'], 7)) !== null) {
                $orders[substr($tile['key'], 7)] = (int) $tile['value'];
            }
        }

        return array_filter([
            'ads' => $ads !== null && $has(DashboardAccess::CONTENT) ? $ads['distribution'] : [],
            'orders' => $orders,
        ]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function recentFor(callable $has): array
    {
        $lists = [
            'pending_ads' => [DashboardAccess::REVIEW, fn (): array => $this->recent->pendingAds()],
            'reports' => [DashboardAccess::MODERATION, fn (): array => $this->recent->reports()],
            'tickets' => [DashboardAccess::SUPPORT, fn (): array => $this->recent->tickets()],
            'signups' => [DashboardAccess::USERS, fn (): array => $this->recent->signups()],
            'orders' => [DashboardAccess::FINANCE, fn (): array => $this->recent->orders()],
            'top_followed' => [DashboardAccess::USERS, fn (): array => $this->follows->topBusinesses()],
        ];

        $recent = [];
        foreach ($lists as $key => [$section, $load]) {
            if ($has($section)) {
                $recent[$key] = $load();
            }
        }

        return $recent;
    }
}
