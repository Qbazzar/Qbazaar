<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Seeded randomness, so two runs with the same counts build the same demo
 * and a bug seen in one walkthrough can be reproduced in the next.
 */
final class DemoRandom
{
    private readonly Randomizer $randomizer;

    public function __construct(int $seed = 974)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    public function chance(float $probability): bool
    {
        return $this->randomizer->getFloat(0, 1) < $probability;
    }

    /**
     * @template T
     *
     * @param array<array-key, T> $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->randomizer->pickArrayKeys($items, 1)[0]];
    }

    /**
     * Up to $count distinct items, in random order.
     *
     * @template T
     *
     * @param array<array-key, T> $items
     * @return list<T>
     */
    public function sample(array $items, int $count): array
    {
        $count = min($count, count($items));

        if ($count <= 0) {
            return [];
        }

        $keys = $this->randomizer->shuffleArray($this->randomizer->pickArrayKeys($items, $count));

        return array_map(static fn (int|string $key): mixed => $items[$key], $keys);
    }

    /**
     * A price inside the range, rounded the way sellers round (to 5, 50, 500…).
     */
    public function price(int $min, int $max): int
    {
        $raw = $this->int($min, $max);
        $step = match (true) {
            $raw >= 100_000 => 5_000,
            $raw >= 10_000 => 500,
            $raw >= 1_000 => 50,
            $raw >= 100 => 5,
            default => 1,
        };

        return max($min, intdiv($raw, $step) * $step);
    }
}
