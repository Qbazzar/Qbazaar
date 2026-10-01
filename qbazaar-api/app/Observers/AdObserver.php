<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ad;
use App\Observers\Concerns\ResolvesActivityCauser;
use BackedEnum;

/**
 * Mirrors meaningful Ad lifecycle changes onto the activity-log table, one
 * row per save. A status change is logged as `status_changed` so the admin
 * can find lifecycle transitions by event name; any other edit of a watched
 * field is `ad_updated`. Both carry the changed fields with old/new values,
 * except the description, which is recorded as changed without its text.
 */
class AdObserver
{
    use ResolvesActivityCauser;

    private const LOGGED_FIELDS = ['status', 'title', 'price', 'description', 'category_id', 'location_id'];

    private const UNDIFFED_FIELDS = ['description'];

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
        $fields = array_values(array_filter(self::LOGGED_FIELDS, fn (string $field): bool => $ad->wasChanged($field)));

        if ($fields === []) {
            return;
        }

        $diffed = array_diff($fields, self::UNDIFFED_FIELDS);
        $statusChanged = in_array('status', $fields, true);

        activity('ad')
            ->performedOn($ad)
            ->causedBy($this->causer($ad->user))
            ->event($statusChanged ? 'status_changed' : 'ad_updated')
            ->withProperties([
                'fields' => $fields,
                'old' => $this->values($diffed, fn (string $field): mixed => $ad->getOriginal($field)),
                'new' => $this->values($diffed, fn (string $field): mixed => $ad->getAttribute($field)),
            ])
            ->log($statusChanged ? 'Ad status changed' : 'Ad updated');
    }

    public function deleted(Ad $ad): void
    {
        activity('ad')
            ->performedOn($ad)
            ->causedBy($this->causer($ad->user))
            ->event('ad_deleted')
            ->log('Ad deleted');
    }

    public function restored(Ad $ad): void
    {
        activity('ad')
            ->performedOn($ad)
            ->causedBy($this->causer($ad->user))
            ->event('ad_restored')
            ->log('Ad restored');
    }

    /**
     * @param array<int, string> $fields
     * @param callable(string): mixed $read
     * @return array<string, mixed>
     */
    private function values(array $fields, callable $read): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $this->stringify($read($field));
        }

        return $values;
    }

    private function stringify(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}
