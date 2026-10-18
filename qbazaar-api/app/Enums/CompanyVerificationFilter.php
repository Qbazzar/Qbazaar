<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The verified-badge filter of the admin companies list.
 */
enum CompanyVerificationFilter: string
{
    case VERIFIED = 'verified';
    case UNVERIFIED = 'unverified';

    public function label(): string
    {
        return (string) __('admin.companies.filters.' . $this->value);
    }

    /**
     * @return array<string, string> value => label, for the filter select
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
