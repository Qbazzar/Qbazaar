<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Money;

enum SettingType: string
{
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';

    /**
     * Money stays an exact two-decimal string, never a float, on its way to
     * a decimal column comparison.
     */
    public function cast(mixed $value): int|string
    {
        return match ($this) {
            self::INTEGER => (int) $value,
            self::DECIMAL => Money::round(is_int($value) ? $value : (string) $value),
        };
    }

    /**
     * @return list<string>
     */
    public function rules(int|float $min, int|float $max): array
    {
        $typeRules = match ($this) {
            self::INTEGER => ['integer'],
            self::DECIMAL => ['numeric', 'decimal:0,2'],
        };

        return ['required', ...$typeRules, "min:{$min}", "max:{$max}"];
    }
}
