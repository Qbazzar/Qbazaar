<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Iban;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Iban::isValid($value)) {
            $fail('errors.validation.iban')->translate();
        }
    }
}
