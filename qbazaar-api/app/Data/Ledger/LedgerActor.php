<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Enums\LedgerActorType;
use App\Models\User;

final readonly class LedgerActor
{
    private function __construct(
        public LedgerActorType $type,
        public ?string $id,
    ) {}

    public static function system(): self
    {
        return new self(LedgerActorType::SYSTEM, null);
    }

    public static function user(User $user): self
    {
        return new self(LedgerActorType::USER, $user->id);
    }

    public static function admin(User $admin): self
    {
        return new self(LedgerActorType::ADMIN, $admin->id);
    }
}
