<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

/**
 * The few fields <x-admin.user-avatar> reads from a user, rebuilt from the
 * cached payload so the avatar component can be reused without loading models.
 */
final readonly class DashboardPerson
{
    public ?string $email;

    public function __construct(
        private int|string|null $id,
        public string $full_name,
        private ?string $avatar,
    ) {
        $this->email = null;
    }

    /**
     * @param array{id: int|string|null, name: string, avatar: string|null} $person
     */
    public static function fromPayload(array $person): self
    {
        return new self($person['id'], $person['name'], $person['avatar']);
    }

    public function getKey(): int|string|null
    {
        return $this->id;
    }

    public function avatarThumbUrl(): ?string
    {
        return $this->avatar;
    }
}
