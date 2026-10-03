<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query for `GET /api/v1/account/orders`.
 *
 * @queryParam role string `buyer` (purchases, default) or `seller` (sales).
 * @queryParam status string Only orders in this status.
 */
class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in(['buyer', 'seller'])],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
        ];
    }

    /**
     * @return 'buyer'|'seller'
     */
    public function role(): string
    {
        return $this->validated('role') === 'seller' ? 'seller' : 'buyer';
    }

    public function status(): ?OrderStatus
    {
        $status = $this->validated('status');

        return is_string($status) ? OrderStatus::from($status) : null;
    }
}
