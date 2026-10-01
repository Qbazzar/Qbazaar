<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the owner's business profile with the fields sent.
 */
class UpdateBusinessProfileAction
{
    /**
     * @param array<string, mixed> $attributes validated fields
     *
     * @throws DomainException
     */
    public function __invoke(User $owner, array $attributes): BusinessProfile
    {
        if (! $owner->isBusiness()) {
            throw new DomainException(ErrorCode::BUSINESS_ACCOUNT_REQUIRED);
        }

        return DB::transaction(function () use ($owner, $attributes): BusinessProfile {
            // firstOrCreate settles a concurrent first save on the primary key; the lock then serialises the edits.
            $owner->businessProfile()->firstOrCreate();

            /** @var BusinessProfile $profile */
            $profile = BusinessProfile::query()->lockForUpdate()->findOrFail($owner->id);
            $profile->fill($attributes);
            $changed = array_keys($profile->getDirty());
            $profile->save();

            if ($changed !== []) {
                activity('user')
                    ->performedOn($owner)
                    ->causedBy($owner)
                    ->event('business_profile_updated')
                    ->withProperties(['fields' => $changed])
                    ->log('Business profile updated');
            }

            return $profile;
        });
    }
}
