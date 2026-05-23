<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use BackedEnum;

/**
 * Mirrors every meaningful change to a User onto the activity-log table.
 *
 * Why one record per change-type instead of one giant "updated" record?
 *  - The log table is queried in the admin by event name (e.g. show me every
 *    password change for this user across last 30 days). Splitting by intent
 *    keeps those queries cheap and indexable.
 *  - Different events carry different sensitivity (password change is an
 *    audit-grade event; avatar change is not). Future retention policies can
 *    treat them separately.
 *
 * Tracked events:
 *  - sign-up        — on created()
 *  - status change  — on updated() when `status` was dirty
 *  - password change — on updated() when `password` was dirty
 *  - email change   — on updated() when `email` was dirty
 *  - phone change   — on updated() when `phone` was dirty
 *  - account deleted — on deleted() (soft-delete also counts here)
 *
 * Hashes / passwords are NEVER written to the log. Only the *fact* that they
 * changed is recorded.
 */
class UserObserver
{
    public function created(User $user): void
    {
        activity('user')
            ->performedOn($user)
            ->causedBy($user)
            ->event('signed_up')
            ->withProperties([
                'account_type' => $user->account_type->value,
                'language' => $user->language->value,
            ])
            ->log('User signed up');
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('deletion_requested_at') && $user->deletion_requested_at !== null) {
            // Capture the dedicated "user asked us to wipe their account"
            // event in addition to the status_changed row, so support staff
            // can answer "when did they request deletion?" with a single
            // indexable event name. The optional `reason` is logged
            // separately by RequestAccountDeletionAction.
            activity('user')
                ->performedOn($user)
                ->causedBy($user)
                ->event('account_deletion_requested')
                ->withProperties([
                    'requested_at' => $user->deletion_requested_at->toIso8601String(),
                ])
                ->log('User requested account deletion');
        }

        if ($user->wasChanged('status')) {
            $this->logFieldChange($user, 'status_changed', 'status', 'Status changed');
        }

        if ($user->wasChanged('password')) {
            // We deliberately omit the old/new hash values — the activity row
            // only records the fact that a change happened.
            activity('user')
                ->performedOn($user)
                ->causedBy($user)
                ->event('password_changed')
                ->log('Password changed');
        }

        if ($user->wasChanged('email')) {
            $this->logFieldChange($user, 'email_changed', 'email', 'Email changed');
        }

        if ($user->wasChanged('phone')) {
            $this->logFieldChange($user, 'phone_changed', 'phone', 'Phone changed');
        }
    }

    public function deleted(User $user): void
    {
        activity('user')
            ->performedOn($user)
            ->causedBy($user)
            ->event('deleted')
            ->log('User account deleted');
    }

    /**
     * Common shape for "field X went from A to B" rows. The activity log
     * stores `old` and `new` so the admin can render a diff without
     * cross-referencing other tables.
     */
    private function logFieldChange(User $user, string $event, string $field, string $description): void
    {
        $original = $user->getOriginal($field);
        $new = $user->getAttribute($field);

        activity('user')
            ->performedOn($user)
            ->causedBy($user)
            ->event($event)
            ->withProperties([
                'old' => $this->stringify($original),
                'new' => $this->stringify($new),
            ])
            ->log($description);
    }

    /**
     * Activity log stores property values as JSON; enums need their scalar
     * form, not the enum instance itself.
     */
    private function stringify(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}
