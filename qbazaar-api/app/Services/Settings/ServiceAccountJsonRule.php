<?php

declare(strict_types=1);

namespace App\Services\Settings;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Firebase service-account key: a JSON object carrying project_id and client_email.
 */
final class ServiceAccountJsonRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($decoded)
            || ! is_string($decoded['project_id'] ?? null) || $decoded['project_id'] === ''
            || ! is_string($decoded['client_email'] ?? null) || $decoded['client_email'] === '') {
            $fail(__('admin.integrations.invalid_service_account'));
        }
    }
}
