<?php

declare(strict_types=1);

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Closes the mass-write paths of an Eloquent query (`Model::query()->update()`
 * and friends), which skip model events and so would slip past AppendOnly.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class AppendOnlyBuilder extends Builder
{
    /**
     * @param array<mixed> $values
     */
    public function update(array $values): never
    {
        $this->refuse();
    }

    /**
     * @param array<mixed> $values
     * @param array<mixed>|string $uniqueBy
     * @param array<mixed>|null $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        $this->refuse();
    }

    /**
     * @param array<mixed> $extra
     */
    public function increment($column, $amount = 1, array $extra = []): never
    {
        $this->refuse();
    }

    /**
     * @param array<mixed> $extra
     */
    public function decrement($column, $amount = 1, array $extra = []): never
    {
        $this->refuse();
    }

    public function delete(): never
    {
        $this->refuse();
    }

    public function forceDelete(): never
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new LogicException($this->model::class . ' rows are append-only; post a reversal instead.');
    }
}
