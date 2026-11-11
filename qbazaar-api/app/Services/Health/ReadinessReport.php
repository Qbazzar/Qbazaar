<?php

declare(strict_types=1);

namespace App\Services\Health;

/**
 * The result of one {@see ReadinessProbe} run.
 *
 * `down` (a critical dependency failed) is what an uptime monitor pages on;
 * `degraded` means the API still answers but a background part (search,
 * scheduler, queue workers) needs attention.
 */
final readonly class ReadinessReport
{
    public const string OK = 'ok';

    public const string DEGRADED = 'degraded';

    public const string DOWN = 'down';

    /**
     * @param array<string, CheckStatus> $checks
     * @param list<string> $critical names of the checks the API cannot serve without
     */
    public function __construct(
        private array $checks,
        private array $critical,
    ) {}

    public function isReady(): bool
    {
        foreach ($this->critical as $name) {
            if (! ($this->checks[$name] ?? CheckStatus::FAIL)->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    public function status(): string
    {
        if (! $this->isReady()) {
            return self::DOWN;
        }

        foreach ($this->checks as $status) {
            if (! $status->isHealthy()) {
                return self::DEGRADED;
            }
        }

        return self::OK;
    }

    /**
     * @return array{status: string, checks: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status(),
            'checks' => array_map(fn (CheckStatus $status): string => $status->value, $this->checks),
        ];
    }
}
