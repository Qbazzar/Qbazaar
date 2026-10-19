<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\ReportTarget;
use App\Models\Ad;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What a report's sanctions can reach: the reported ad, if any, and the
 * account behind the report (the reported user, the ad's owner or the
 * message's sender). Deleted records reach nothing: they cannot be
 * sanctioned again.
 */
final readonly class ReportTargets
{
    public function __construct(
        public ?Ad $ad,
        public ?User $account,
    ) {}

    /**
     * The same targets from a record the caller already loaded (the report
     * page reads it for its preview), so offering the actions costs no second
     * read. A reported message reaches its sender only when the sender was
     * loaded with it, which happens only for staff who may act on the report.
     */
    public static function fromLoaded(ReportTarget $type, ?Model $target): self
    {
        if ($target === null || (method_exists($target, 'trashed') && $target->trashed())) {
            return new self(null, null);
        }

        return match (true) {
            $type === ReportTarget::AD && $target instanceof Ad => new self($target, self::live($target->user)),
            $type === ReportTarget::USER && $target instanceof User => new self(null, $target),
            $type === ReportTarget::MESSAGE && $target instanceof Message => new self(
                null,
                $target->relationLoaded('sender') ? self::live($target->sender) : null,
            ),
            default => new self(null, null),
        };
    }

    private static function live(?User $user): ?User
    {
        return $user !== null && ! $user->trashed() ? $user : null;
    }
}
