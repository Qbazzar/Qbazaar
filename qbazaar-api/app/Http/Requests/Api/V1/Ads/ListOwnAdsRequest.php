<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Enums\AdStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string of `GET /api/v1/account/ads`. */
class ListOwnAdsRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::enum(AdStatus::class)],
        ];
    }

    public function status(): ?AdStatus
    {
        $status = $this->validated('status');

        return is_string($status) ? AdStatus::from($status) : null;
    }
}
