<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query for `GET /api/v1/account/notifications`.
 *
 * @queryParam unread boolean Only unread notifications.
 * @queryParam category string A full category (`ad.price_changed`) or a group prefix (`ad`, `offer`).
 */
class ListNotificationsRequest extends FormRequest
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
            'unread' => ['sometimes', 'boolean'],
            'category' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[a-z_]+(\.[a-z_]+)*$/'],
        ];
    }

    public function category(): ?string
    {
        $category = $this->validated('category');

        return is_string($category) && $category !== '' ? $category : null;
    }
}
