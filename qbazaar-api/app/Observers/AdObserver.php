<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ad;
use App\Observers\Concerns\ResolvesActivityCauser;
use BackedEnum;

/**
 * Mirrors meaningful Ad lifecycle changes onto the activity-log table.
 *
 * Pattern matches {@see UserObserver}:
 *  - One event-name per intent ("status_changed", "price_changed", …) so the
 *    admin can index queries by event without parsing the diff blob.
 *  - The Ad model also uses Spatie\LogsActivity for raw attribute snapshots
 *    (old/new diff) — this observer adds the *named* per-field events on top
 *    so we have both views available.
 */
class AdObserver
{
    use ResolvesActivityCauser;

    public function created(Ad $ad): void
    {
        activity('ad')
            ->performedOn($ad)
            ->causedBy($this->causer($ad->user))
            ->event('ad_created')
            ->withProperties([
                'status' => $ad->status->value,
                'category_id' => $ad->category_id,
                'location_id' => $ad->location_id,
            ])
            ->log('Ad created');
    }

    public function updated(Ad $ad): void
    {
        if ($ad->wasChanged('status')) {
            $this->logFieldChange($ad, 'status_changed', 'status', 'Ad status changed');
        }

        if ($ad->wasChanged('title')) {
            $this->logFieldChange($ad, 'title_changed', 'title', 'Ad title changed');
        }

        if ($ad->wasChanged('price')) {
            $this->logFieldChange($ad, 'price_changed', 'price', 'Ad price changed');
        }

        if ($ad->wasChanged('description')) {
            // Description is logged as a fact, not a diff — descriptions are
            // long and the activity-log JSON column would balloon. The raw
            // old/new is still available through the Spatie LogsActivity
            // generic "updated" row.
            activity('ad')
                ->performedOn($ad)
                ->causedBy($this->causer($ad->user))
                ->event('description_changed')
                ->log('Ad description changed');
        }
    }

    public function deleted(Ad $ad): void
    {
        activity('ad')
            ->performedOn($ad)
            ->causedBy($this->causer($ad->user))
            ->event('ad_deleted')
            ->log('Ad deleted');
    }

    private function logFieldChange(Ad $ad, string $event, string $field, string $description): void
    {
        $original = $ad->getOriginal($field);
        $new = $ad->getAttribute($field);

        activity('ad')
            ->performedOn($ad)
            ->causedBy($this->causer($ad->user))
            ->event($event)
            ->withProperties([
                'old' => $this->stringify($original),
                'new' => $this->stringify($new),
            ])
            ->log($description);
    }

    private function stringify(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}
