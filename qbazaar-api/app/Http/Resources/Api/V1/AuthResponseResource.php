<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Services\Auth\TokenPair;

/**
 * Plain shaper for the `{ user, tokens }` payload returned by register / login
 * / refresh.
 *
 * We deliberately do NOT extend JsonResource because the global
 * ApiResponseWrapper middleware already takes care of the success envelope —
 * and JsonResource's auto-`{data: ...}` wrapping would double-nest and confuse
 * the wrapper.
 */
final readonly class AuthResponseResource
{
    public function __construct(
        public User $user,
        public TokenPair $tokens,
    ) {}

    /**
     * @return array{user: array<string,mixed>, tokens: array<string,mixed>}
     */
    public function toArray(): array
    {
        return [
            'user' => (new UserResource($this->user))->resolve(),
            'tokens' => (new TokenResource($this->tokens))->resolve(),
        ];
    }
}
