<?php

declare(strict_types=1);

namespace App\Enums\Contracts;

/**
 * A status-like enum the admin panel renders as <x-admin.badge :status="...">.
 */
interface Badgeable
{
    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array;

    /**
     * @return 'neutral'|'success'|'warning'|'danger'|'info'|'brand'|'violet'
     */
    public function tone(): string;
}
