<?php

declare(strict_types=1);

use App\Enums\QueueName;
use Illuminate\Support\Facades\File;

/**
 * Jobs pushed onto a queue that no Horizon supervisor consumes sit in Redis
 * forever without failing, so this guards the gap statically.
 *
 * @return list<string>
 */
function queuesDeclaredInApp(): array
{
    $queues = [
        (string) config('queue.connections.redis.queue'),
        (string) config('scout.queue.queue'),
        (string) config('media-library.queue_name'),
        ...array_map(fn (QueueName $queue): string => $queue->value, QueueName::cases()),
    ];

    foreach (File::allFiles(app_path()) as $file) {
        preg_match_all(
            '/(?:onQueue\(\s*|\$queue\s*=\s*)[\'"]([a-z0-9_-]+)[\'"]/i',
            $file->getContents(),
            $matches,
        );

        array_push($queues, ...$matches[1]);
    }

    return array_values(array_unique($queues));
}

/**
 * @return array<string, array<string, mixed>>
 */
function horizonSupervisors(string $environment): array
{
    return array_replace_recursive(
        config('horizon.defaults', []),
        config("horizon.environments.{$environment}", []),
    );
}

/**
 * @return list<string>
 */
function queuesConsumedByHorizon(string $environment): array
{
    $queues = [];

    foreach (horizonSupervisors($environment) as $supervisor) {
        $connection = $supervisor['connection'] ?? 'redis';

        if (config("queue.connections.{$connection}.driver") !== 'redis') {
            continue;
        }

        foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
            $queues[] = (string) $queue;
        }
    }

    return array_values(array_unique($queues));
}

it('finds the queues the application dispatches onto', function (): void {
    expect(queuesDeclaredInApp())->toContain('realtime', 'notifications', 'default', 'search', 'media', 'low');
});

it('consumes every queue the application dispatches onto', function (string $environment): void {
    $consumed = queuesConsumedByHorizon($environment);

    foreach (queuesDeclaredInApp() as $queue) {
        expect($consumed)->toContain($queue);
    }
})->with(['production', 'local']);

it('gives every queue its own supervisor', function (string $environment): void {
    $owners = [];

    foreach (horizonSupervisors($environment) as $name => $supervisor) {
        foreach ((array) $supervisor['queue'] as $queue) {
            $owners[$queue][] = $name;
        }
    }

    foreach ($owners as $queue => $supervisors) {
        expect($supervisors)->toHaveCount(1, "Queue [{$queue}] is read by more than one supervisor.");
    }
})->with(['production', 'local']);

it('keeps every supervisor timeout below the retry_after of its connection', function (string $environment): void {
    foreach (horizonSupervisors($environment) as $name => $supervisor) {
        $retryAfter = (int) config("queue.connections.{$supervisor['connection']}.retry_after");

        expect($supervisor['timeout'])->toBeLessThan($retryAfter, "Supervisor [{$name}] outlives its retry_after.");
    }
})->with(['production', 'local']);

it('pushes jobs dispatched inside a transaction only after it commits', function (): void {
    expect(config('queue.connections.redis.after_commit'))->toBeTrue()
        ->and(config('queue.connections.redis-long.after_commit'))->toBeTrue();
});
