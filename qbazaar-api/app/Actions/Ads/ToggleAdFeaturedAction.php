<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Models\Ad;

class ToggleAdFeaturedAction
{
    /** Returns the new featured state. */
    public function __invoke(Ad $ad): bool
    {
        $featured = ! $ad->featured;
        $ad->forceFill(['featured' => $featured])->save();

        return $featured;
    }
}
