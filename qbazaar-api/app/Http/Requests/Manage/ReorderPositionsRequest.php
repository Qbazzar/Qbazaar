<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage;

use App\Support\ManualOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Positions staff typed next to the rows of a hand-sorted list (featured
 * companies, home sections): `positions[<row key>] = <1-based position>`.
 * A blank position keeps the row in place; keys the list does not hold are
 * ignored by {@see ManualOrder}.
 */
class ReorderPositionsRequest extends FormRequest
{
    private const int MAX_ROWS = 100;

    private const int MAX_POSITION = 999;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'positions' => ['required', 'array', 'max:' . self::MAX_ROWS],
            'positions.*' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_POSITION],
        ];
    }

    /**
     * @return array<array-key, int|null> row key => position
     */
    public function positions(): array
    {
        /** @var array<array-key, mixed> $positions */
        $positions = (array) $this->validated('positions');

        return array_map(static fn (mixed $position): ?int => $position === null ? null : (int) $position, $positions);
    }
}
