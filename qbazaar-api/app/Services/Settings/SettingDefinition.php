<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\SettingGroup;
use App\Enums\SettingType;

final readonly class SettingDefinition
{
    private function __construct(
        public SettingType $type,
        public SettingGroup $group,
        public string $defaultConfigKey,
        public int|float $min,
        public int|float $max,
    ) {}

    public static function integer(SettingGroup $group, string $defaultConfigKey, int $min, int $max): self
    {
        return new self(SettingType::INTEGER, $group, $defaultConfigKey, $min, $max);
    }

    public static function decimal(SettingGroup $group, string $defaultConfigKey, float $min, float $max): self
    {
        return new self(SettingType::DECIMAL, $group, $defaultConfigKey, $min, $max);
    }

    public function defaultValue(): int|string
    {
        return $this->type->cast(config($this->defaultConfigKey));
    }

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        return $this->type->rules($this->min, $this->max);
    }

    public function step(): string
    {
        return $this->type === SettingType::DECIMAL ? '0.01' : '1';
    }
}
