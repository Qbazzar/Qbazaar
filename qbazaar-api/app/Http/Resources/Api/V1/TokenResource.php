<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Services\Auth\TokenPair;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a plain `TokenPair` array — see App\Services\Auth\TokenPair.
 *
 * @mixin TokenPair
 */
class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TokenPair $pair */
        $pair = $this->resource;

        return [
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn,
        ];
    }
}
