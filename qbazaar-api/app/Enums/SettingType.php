<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingType: string
{
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';

    /**
     * Money stays a fixed two-decimal string so it never picks up float noise
     * on its way to a decimal(12,2) comparison.
     */
    public function cast(mixed $value): int|string
    {
        return match ($this) {
            self::INTEGER => (int) $value,
            self::DECIMAL => number_format((float) $value, 2, '.', ''),
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
