<?php

declare(strict_types=1);

use App\Enums\QueueName;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Symfony\Component\Finder\Finder;

/**
 * Every class the application queues: jobs, queued listeners, queued
 * notifications and broadcast events. Datasets are built before the app
 * boots, hence Finder and a path relative to this file.
 *
 * @return array<string, array{class-string}>
 */
function queuedWorkClasses(): array
{
    $classes = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 3) . '/app')->name('*.php') as $file) {
        $class = 'App\\' . Str::of($file->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString();

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        $queued = $reflection->implementsInterface(ShouldQueue::class)
            || ($reflection->implementsInterface(ShouldBroadcast::class) && ! $reflection->implementsInterface(ShouldBroadcastNow::class));

        if ($queued && ! $reflection->isAbstract()) {
            $classes[class_basename($class)] = [$class];
        }
    }

    return $classes;
}

/**
 * The queues a queued class lands on, read the way Laravel reads them.
 *
 * @param class-string $class
 * @return list<string>
 */
function queuesOf(string $class): array
{
    $reflection = new ReflectionClass($class);

    if ($reflection->isSubclassOf(PerformConversionsJob::class)) {
        return [(string) config('media-library.queue_name')];
    }

    if ($reflection->hasMethod('handle') && ! $reflection->hasMethod('onQueue')) {
        return [(string) $reflection->getDefaultProperties()['queue']];
    }

    $instance = $reflection->hasMethod('broadcastQueue') || $reflection->hasMethod('viaQueues')
        ? $reflection->newInstanceWithoutConstructor()
        : newJobWithPlaceholderArguments($reflection);

    return match (true) {
        $instance instanceof ShouldBroadcast && method_exists($instance, 'broadcastQueue') => [(string) $instance->broadcastQueue()],
        $instance instanceof Notification && method_exists($instance, 'viaQueues') => array_values(array_unique(array_map(strval(...), $instance->viaQueues()))),
        property_exists($instance, 'queue') => [(string) $instance->queue],
        default => throw new LogicException("Cannot tell the queue of [{$class}]."),
    };
}

/**
 * @param ReflectionClass<object> $reflection
 */
function newJobWithPlaceholderArguments(ReflectionClass $reflection): object
{
    $arguments = array_map(
        fn (ReflectionParameter $parameter): mixed => match ((string) $parameter->getType()) {
            'string' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
            'array' => [],
            'int' => 0,
            default => throw new LogicException("Teach the test to build {$reflection->getName()}."),
        },
        $reflection->getConstructor()?->getParameters() ?? [],
    );

    return $reflection->newInstanceArgs($arguments);
}

function retryAfterForQueue(string $queue): int
{
    foreach (config('horizon.defaults') as $supervisor) {
        if (in_array($queue, (array) $supervisor['queue'], true)) {
            return (int) config("queue.connections.{$supervisor['connection']}.retry_after");
        }
    }

    throw new LogicException("No supervisor reads [{$queue}].");
}

it('finds the queued work', function (): void {
    expect(queuedWorkClasses())->toHaveKeys(['MessageSent', 'SendChatPushNotifications', 'OtpNotification', 'ExpireOldAdsJob', 'PerformConversionsJob']);
});

/**
 * @param class-string $class
 * @return array<string, mixed>
 */
function defaultPropertiesOf(string $class): array
{
    return (new ReflectionClass($class))->getDefaultProperties();
}

it('sets explicit tries, backoff and timeout', function (string $class): void {
    /** @var class-string $class */
    $defaults = defaultPropertiesOf($class);

    expect($defaults['tries'] ?? null)->toBeInt()->toBeGreaterThan(0)
        ->and($defaults['timeout'] ?? null)->toBeInt()->toBeGreaterThan(0)
        ->and(($defaults['backoff'] ?? null) !== null || method_exists($class, 'backoff'))->toBeTrue();
})->with(queuedWorkClasses());

it('runs on a known queue and finishes within its retry_after', function (string $class): void {
    /** @var class-string $class */
    $timeout = defaultPropertiesOf($class)['timeout'];
    $known = array_map(fn (QueueName $queue): string => $queue->value, QueueName::cases());

    foreach (queuesOf($class) as $queue) {
        expect($known)->toContain($queue)
            ->and($timeout)->toBeLessThan(retryAfterForQueue($queue));
    }
})->with(queuedWorkClasses());
