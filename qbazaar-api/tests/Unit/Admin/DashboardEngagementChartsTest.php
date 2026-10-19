<?php

declare(strict_types=1);

use App\Services\Admin\Dashboard\DashboardLayout;

/**
 * @param array<string, list<array<string, mixed>>> $series
 * @param list<array<string, mixed>> $tiles
 */
function engagementLayout(array $series, array $tiles = []): DashboardLayout
{
    return new DashboardLayout([
        'groups' => [['key' => 'engagement', 'icon' => 'chat', 'tiles' => $tiles]],
        'series' => $series,
        'distributions' => [],
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function engagementPoints(int $count): array
{
    return [['date' => '2026-10-17', 'count' => $count], ['date' => '2026-10-18', 'count' => 0]];
}

it('builds one chart per engagement series, in a fixed order, with the matching tile figure', function (): void {
    app()->setLocale('en');
    $layout = engagementLayout(
        ['follows' => engagementPoints(4), 'users' => engagementPoints(9), 'messages' => engagementPoints(120)],
        [
            ['key' => 'messages_new', 'kind' => 'count', 'value' => 1500, 'previous' => 1000, 'change' => 50],
            ['key' => 'follows_new', 'kind' => 'count', 'value' => 7, 'previous' => 0, 'change' => null],
        ],
    );

    $charts = $layout->engagementCharts();

    expect(array_column($charts, 'key'))->toBe(['messages', 'follows'])
        ->and($charts[0])->toMatchArray(['label' => 'Messages', 'value' => '1,500', 'change' => 50])
        ->and($charts[0]['series'][0])->toMatchArray(['tone' => 'slate', 'field' => 'count', 'points' => engagementPoints(120)])
        ->and($charts[1])->toMatchArray(['label' => 'New follows', 'value' => '7', 'change' => null]);
});

it('leaves the figure out when the staff member cannot see the tile', function (): void {
    $charts = engagementLayout(['conversations' => engagementPoints(2)])->engagementCharts();

    expect($charts)->toHaveCount(1)
        ->and($charts[0])->toMatchArray(['key' => 'conversations', 'value' => null, 'change' => false]);
});

it('has nothing to show without engagement series', function (): void {
    expect(engagementLayout(['users' => engagementPoints(3), 'revenue' => []])->engagementCharts())->toBe([]);
});
