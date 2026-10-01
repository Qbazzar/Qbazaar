<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ad;
use App\Services\Ads\AdDetailCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Drops the cached public detail of an ad whenever the ad or one of its
 * images is saved or removed. Registered on both Ad and Media.
 */
class AdDetailCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly AdDetailCache $cache) {}

    public function saved(Ad|Media $model): void
    {
        $this->forget($model);
    }

    public function deleted(Ad|Media $model): void
    {
        $this->forget($model);
    }

    public function restored(Ad $ad): void
    {
        $this->forget($ad);
    }

    private function forget(Ad|Media $model): void
    {
        if ($model instanceof Ad) {
            $this->cache->forget($model->id);

            return;
        }

        if ($model->model_type === (new Ad)->getMorphClass()) {
            $this->cache->forget((string) $model->model_id);
        }
    }
}
