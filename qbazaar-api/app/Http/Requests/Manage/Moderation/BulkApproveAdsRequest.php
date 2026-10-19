<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Moderation;

use App\Http\Requests\Manage\Moderation\Concerns\ValidatesSelectedAds;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve the selected ads from the review queue; approval needs no reason.
 */
class BulkApproveAdsRequest extends FormRequest
{
    use ValidatesSelectedAds;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->selectedAdRules();
    }
}
