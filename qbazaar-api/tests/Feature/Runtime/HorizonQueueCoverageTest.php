<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Jobs pushed onto a queue that no Horizon supervisor consumes sit in Redis
 * forever without failing, so this guards the gap statically.
 */
function queuesDeclaredInApp(): array
{
    $queues = [(string) config('queue.connections.redis.queue')];

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

function queuesConsumedByHorizon(string $environment): array
{
    $supervisors = array_replace_recursive(
        config('horizon.defaults', []),
        config("horizon.environments.{$environment}", []),
    );

    return collect($supervisors)
        ->filter(fn (array $supervisor): bool => ($supervisor['connection'] ?? 'redis') === 'redis')
        ->flatMap(fn (array $supervisor): array => (array) ($supervisor['queue'] ?? []))
        ->unique()
        ->values()
        ->all();
}

it('finds the queues the application dispatches onto', function (): void {
    expect(queuesDeclaredInApp())->toContain('default', 'low');
});

it('consumes every queue the application dispatches onto', function (string $environment): void {
    $consumed = queuesConsumedByHorizon($environment);

    foreach (queuesDeclaredInApp() as $queue) {
        expect($consumed)->toContain($queue);
    }
})->with(['production', 'local']);
