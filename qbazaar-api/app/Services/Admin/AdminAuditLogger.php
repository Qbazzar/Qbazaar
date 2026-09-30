<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Model;

/**
 * Single writer for the admin audit trail: every entry is caused by the
 * acting staff member, never by the account they acted on.
 *
 * Scoped per request so AuditAdminMutations can tell whether an action
 * already wrote a detailed entry and skip its generic one.
 */
#[Scoped]
class AdminAuditLogger
{
    public const LOG_NAME = 'admin';

    private bool $recorded = false;

    /**
     * @param array<string, mixed> $properties
     */
    public function record(User $admin, string $event, ?Model $subject = null, array $properties = []): void
    {
        $entry = activity(self::LOG_NAME)
            ->causedBy($admin)
            ->event($event)
            ->withProperties($properties);

        if ($subject !== null) {
            $entry->performedOn($subject);
        }

        $entry->log($event);

        $this->recorded = true;
    }

    public function hasRecorded(): bool
    {
        return $this->recorded;
    }

    public function forgetRecorded(): void
    {
        $this->recorded = false;
    }
}
