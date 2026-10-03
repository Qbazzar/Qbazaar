<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet\Concerns;

use App\Support\Money;

/**
 * A positive QAR amount with at most two decimals, handed on as an exact
 * decimal string. Clients should send it as a string ("150.25").
 */
trait ValidatesMoneyAmount
{
    /**
     * @return list<string>
     */
    protected function amountRules(): array
    {
        return ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'];
    }

    public function amount(): string
    {
        return Money::of((string) $this->validated('amount'));
    }
}
