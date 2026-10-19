<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Moderation;

use App\Http\Requests\Manage\Moderation\Concerns\ValidatesSelectedAds;

/**
 * A bulk move on selected ads that needs a reason: removing them from the ads
 * list or rejecting them from the review queue.
 */
class BulkSanctionAdsRequest extends SanctionRequest
{
    use ValidatesSelectedAds;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->selectedAdRules(), ...parent::rules()];
    }
}
