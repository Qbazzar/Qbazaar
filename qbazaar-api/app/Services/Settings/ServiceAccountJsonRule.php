<?php

declare(strict_types=1);

namespace App\Services\Settings;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Firebase service-account key: a JSON object of type service_account with project_id, client_email and private_key.
 */
final class ServiceAccountJsonRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($decoded)
            || ($decoded['type'] ?? null) !== 'service_account'
            || ! $this->filled($decoded, ['project_id', 'client_email', 'private_key'])) {
            $fail(__('admin.integrations.invalid_service_account'));
        }
    }

    /**
     * @param array<string, mixed> $decoded
     * @param list<string> $keys
     */
    private function filled(array $decoded, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! is_string($decoded[$key] ?? null) || $decoded[$key] === '') {
                return false;
            }
        }

        return true;
    }
}
