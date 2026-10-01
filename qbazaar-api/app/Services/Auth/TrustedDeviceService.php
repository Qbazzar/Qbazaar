<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Carbon;

class TrustedDeviceService
{
    public function isTrusted(User $user, DeviceContext $device): bool
    {
        return TrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('device_hash', $device->hash)
            ->exists();
    }

    /**
     * Records the device as trusted (or refreshes its last use).
     *
     * @return bool true when the device was not trusted before
     */
    public function trust(User $user, DeviceContext $device): bool
    {
        $trusted = TrustedDevice::query()->firstOrCreate(
            ['user_id' => $user->id, 'device_hash' => $device->hash],
            ['label' => $device->label, 'last_ip' => $device->ip, 'last_used_at' => Carbon::now()],
        );

        if (! $trusted->wasRecentlyCreated) {
            $trusted->forceFill([
                'label' => $device->label,
                'last_ip' => $device->ip,
                'last_used_at' => Carbon::now(),
            ])->save();
        }

        return $trusted->wasRecentlyCreated;
    }
}
