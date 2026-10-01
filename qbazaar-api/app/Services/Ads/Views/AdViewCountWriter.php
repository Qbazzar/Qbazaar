<?php

declare(strict_types=1);

namespace App\Services\Ads\Views;

use Illuminate\Support\Facades\DB;

/**
 * Adds buffered view counts to `ads.views_count`, one UPDATE per chunk.
 * `updated_at` moves as it did for a per-view increment, which is what
 * SyncAdViewCountsJob uses to find the counters it must send to search.
 */
class AdViewCountWriter
{
    private const CHUNK = 500;

    /**
     * @param array<string, int> $viewsByAd
     */
    public function add(array $viewsByAd): void
    {
        $viewsByAd = array_filter($viewsByAd, fn (int $views): bool => $views > 0);
        // Row locks are taken in key order, so concurrent writers cannot deadlock.
        ksort($viewsByAd);

        // All or nothing, so a retried flush cannot count the chunks of a failed attempt twice.
        DB::transaction(function () use ($viewsByAd): void {
            foreach (array_chunk($viewsByAd, self::CHUNK, true) as $chunk) {
                $this->addChunk($chunk);
            }
        });
    }

    /**
     * @param array<string, int> $chunk
     */
    private function addChunk(array $chunk): void
    {
        $cases = [];
        $bindings = [];

        foreach ($chunk as $adId => $views) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = (string) $adId;
            $bindings[] = $views;
        }

        $ids = array_map(strval(...), array_keys($chunk));
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        DB::update(
            'UPDATE ads SET views_count = views_count + CASE id ' . implode(' ', $cases) . ' ELSE 0 END, updated_at = ? '
            . "WHERE id IN ({$placeholders})",
            [...$bindings, now(), ...$ids],
        );
    }
}
