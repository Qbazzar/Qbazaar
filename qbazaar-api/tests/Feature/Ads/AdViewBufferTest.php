<?php

declare(strict_types=1);

use App\Jobs\Ads\FlushAdViewCountsJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\Views\AdViewCounter;
use App\Services\Ads\Views\AdViewCountWriter;
use App\Services\Ads\Views\RedisAdViewCounter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/**
 * An in-memory stand-in for the few hash commands the buffer uses.
 */
function fakeRedisConnection(): Connection
{
    $client = new class
    {
        /** @var array<string, array<string, int>> */
        public array $hashes = [];

        public function hincrby(string $key, string $field, int $by): int
        {
            return $this->hashes[$key][$field] = ($this->hashes[$key][$field] ?? 0) + $by;
        }

        public function exists(string $key): int
        {
            return isset($this->hashes[$key]) ? 1 : 0;
        }

        public function rename(string $from, string $to): bool
        {
            $this->hashes[$to] = $this->hashes[$from];
            unset($this->hashes[$from]);

            return true;
        }

        /** @return array<string, string> */
        public function hgetall(string $key): array
        {
            return array_map(strval(...), $this->hashes[$key] ?? []);
        }

        public function del(string $key): int
        {
            unset($this->hashes[$key]);

            return 1;
        }
    };

    return new class($client) extends Connection
    {
        public function __construct(object $client)
        {
            $this->client = $client;
        }

        public function createSubscription($channels, Closure $callback, $method = 'subscribe'): void {}
    };
}

beforeEach(function (): void {
    $this->seedReferenceData();
    $seller = User::factory()->create();
    $this->first = $this->makeAd($seller, ['status' => 'active', 'published_at' => now(), 'views_count' => 10]);
    $this->second = $this->makeAd($seller, ['status' => 'active', 'published_at' => now(), 'views_count' => 0]);

    $this->connection = fakeRedisConnection();
    Redis::shouldReceive('connection')->with('default')->andReturn($this->connection);
    $this->counter = new RedisAdViewCounter(app(AdViewCountWriter::class), 'default');
});

function viewsOf(Ad $ad): int
{
    return (int) Ad::query()->whereKey($ad->id)->value('views_count');
}

it('does not touch the ads table while recording views', function (): void {
    DB::enableQueryLog();

    $this->counter->record($this->first->id);
    $this->counter->record($this->first->id);

    expect(DB::getQueryLog())->toBe([])
        ->and(viewsOf($this->first))->toBe(10);
});

it('adds every buffered view in one update on flush and empties the buffer', function (): void {
    $this->counter->record($this->first->id);
    $this->counter->record($this->first->id);
    $this->counter->record($this->second->id);

    DB::enableQueryLog();
    $this->counter->flush();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(viewsOf($this->first))->toBe(12)
        ->and(viewsOf($this->second))->toBe(1);

    $this->counter->flush();

    expect(viewsOf($this->first))->toBe(12);
});

it('writes a batch left over by a failed flush before taking new views', function (): void {
    $this->counter->record($this->first->id);
    // A run that drained the buffer and then died before writing it.
    $this->connection->rename('ads:views:buffer', 'ads:views:draining');
    $this->counter->record($this->second->id);

    $this->counter->flush();

    expect(viewsOf($this->first))->toBe(11)
        ->and(viewsOf($this->second))->toBe(0);

    $this->counter->flush();

    expect(viewsOf($this->second))->toBe(1);
});

it('moves updated_at so the search sync picks up the new counters', function (): void {
    Ad::query()->whereKey($this->first->id)->toBase()->update(['updated_at' => now()->subDay()]);

    $this->counter->record($this->first->id);
    $this->counter->flush();

    expect(Ad::query()->findOrFail($this->first->id)->updated_at->isAfter(now()->subMinute()))->toBeTrue();
});

it('flushes through the bound counter from the scheduled job', function (): void {
    app()->instance(AdViewCounter::class, $this->counter);
    $this->counter->record($this->second->id);

    FlushAdViewCountsJob::dispatchSync();

    expect(viewsOf($this->second))->toBe(1);
});

it('schedules the flush every minute', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'ads.flush-view-counts');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
