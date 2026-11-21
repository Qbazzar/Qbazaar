<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AccountProfileResource extends JsonResource
{
    /**
     * Combines the standard public User shape with the user's own private
     * privacy preferences — the two pieces of state the profile screen
     * always renders together. `has_password` tells the client whether to
     * ask for the current password or for an emailed code before a password
     * change or an account deletion.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...((new UserResource($this->resource))->toArray($request)),
            'has_password' => $this->resource->hasPassword(),
            'privacy_settings' => (new PrivacySettingsResource($this->resource->privacySettings()))->toArray($request),
        ];
    }
}
