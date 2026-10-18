<?php

declare(strict_types=1);

namespace App\Services\Admin\Inbox;

use App\Models\User;
use App\Services\Admin\Dashboard\Collectors\AttentionQueues;
use App\Services\Admin\Moderation\ReviewQueue;
use App\Services\Admin\Moderation\SlaClock;
use App\Services\Catalog\WarmedCache;
use Carbon\CarbonImmutable;

/**
 * The work queues on the staff inbox, each shown only to staff who hold the
 * permission of the page that clears it. The figures are the same for every
 * reader, so each queue is one shared cache entry: at most one aggregate per
 * queue per cache window, however many staff keep the inbox open.
 */
final class WorkQueues
{
    private const string KEY_PREFIX = 'admin:inbox:queue:';

    /** Queue => [permission of the page that clears it, icon]. */
    private const array QUEUES = [
        'pending_ads' => ['ads.approve', 'tag'],
        'open_reports' => ['reports.view', 'flag'],
        'open_tickets' => ['support.view', 'lifebuoy'],
        'disputes' => ['finance.manage', 'banknotes'],
    ];

    public function __construct(
        private readonly WarmedCache $cache,
        private readonly AttentionQueues $attention,
        private readonly ReviewQueue $reviewQueue,
    ) {}

    /**
     * @return list<WorkQueueCard>
     */
    public function for(User $staff, SlaClock $clock): array
    {
        $ttl = max(1, (int) config('qbazaar.admin.inbox_queue_cache_seconds'));
        $cards = [];

        foreach (self::QUEUES as $key => [$permission, $icon]) {
            if (! $staff->can($permission)) {
                continue;
            }

            $figures = $this->cache->get(self::KEY_PREFIX . $key, $ttl, fn (): array => $this->figures($key, $clock));
            $since = $figures['oldest'] !== null ? CarbonImmutable::parse($figures['oldest']) : null;

            $cards[] = new WorkQueueCard(
                key: $key,
                label: (string) __('admin.inbox.queues.' . $key),
                icon: $icon,
                count: $figures['count'],
                href: $figures['href'],
                oldestWaited: $since?->diffForHumans($clock->now, syntax: CarbonImmutable::DIFF_ABSOLUTE, short: true),
                state: $since !== null && $figures['count'] > 0 ? $clock->stateFor($since) : null,
                overdue: $figures['overdue'],
            );
        }

        return $cards;
    }

    /**
     * @return array{count: int, oldest: string|null, overdue: int|null, href: string}
     */
    private function figures(string $key, SlaClock $clock): array
    {
        if ($key === 'pending_ads') {
            $summary = $this->reviewQueue->summary($clock);

            return [
                'count' => $summary->waiting,
                'oldest' => $summary->oldestSince?->toIso8601String(),
                'overdue' => $summary->overdue,
                'href' => route('admin.moderation.queue', [], false),
            ];
        }

        $queue = match ($key) {
            'open_reports' => $this->attention->reports(),
            'open_tickets' => $this->attention->tickets(),
            default => $this->attention->disputes(),
        };

        return [
            'count' => (int) $queue['count'],
            'oldest' => is_string($queue['oldest']) ? $queue['oldest'] : null,
            'overdue' => null,
            'href' => (string) $queue['href'],
        ];
    }
}
