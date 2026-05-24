<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Empty-body request for `POST /api/v1/ads/{id}/publish`.
 *
 * The policy + status-transition rules live in AdPolicy::publish() and the
 * controller's authorize() call. Keeping the request class around (instead
 * of a bare Request) leaves a docblock anchor for ApiDoc and a place to add
 * future fields (eg. publish_now boolean for scheduling).
 */
class PublishAdRequest extends FormRequest
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
        return [];
    }
}
