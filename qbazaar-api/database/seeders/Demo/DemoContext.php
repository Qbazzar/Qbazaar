<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\StaffRole;
use App\Models\User;
use LogicException;

/**
 * What one demo run shares between its steps: the options, the seeded
 * randomness and clock, and the accounts created so far.
 */
final class DemoContext
{
    /** @var array<string, User> staff by role value */
    public array $staff = [];

    /** @var list<User> marketplace members, private and business */
    public array $members = [];

    public function __construct(
        public readonly DemoOptions $options,
        public readonly DemoRandom $random,
        public readonly DemoClock $clock,
    ) {}

    public function staffMember(StaffRole $role): User
    {
        return $this->staff[$role->value] ?? throw new LogicException("No demo {$role->value} account was created.");
    }

    public function moderator(): User
    {
        return $this->staffMember(StaffRole::MODERATOR);
    }

    public function superAdmin(): User
    {
        return $this->staffMember(StaffRole::SUPER_ADMIN);
    }

    /**
     * A member other than the given ones.
     */
    public function memberOtherThan(string ...$userIds): User
    {
        $candidates = array_values(array_filter($this->members, static fn (User $member): bool => ! in_array($member->id, $userIds, true)));

        return $this->random->pick($candidates);
    }

    public function email(string $localPart): string
    {
        return $localPart . '@' . DemoOptions::EMAIL_DOMAIN;
    }
}
