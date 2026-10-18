<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of value a category custom field holds. `range` is a number
 * the admin bounds with a minimum and a maximum, filtered with a slider.
 */
enum CategoryFieldType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case SELECT = 'select';
    case MULTISELECT = 'multiselect';
    case BOOLEAN = 'boolean';
    case RANGE = 'range';

    /**
     * Definitions are admin data and older rows were typed by hand, so an
     * unknown type reads as text, which the validator already treats safely.
     */
    public static function fromStored(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::TEXT) : self::TEXT;
    }

    public function hasOptions(): bool
    {
        return $this === self::SELECT || $this === self::MULTISELECT;
    }

    public function isNumeric(): bool
    {
        return $this === self::NUMBER || $this === self::RANGE;
    }

    /**
     * The search control a filterable field of this type becomes.
     */
    public function filterType(): string
    {
        return match ($this) {
            self::SELECT, self::MULTISELECT => 'select',
            self::NUMBER, self::RANGE => 'range',
            self::BOOLEAN => 'boolean',
            self::TEXT => 'text',
        };
    }

    public function label(): string
    {
        return (string) __('admin.catalog.field_types.' . $this->value);
    }
}
