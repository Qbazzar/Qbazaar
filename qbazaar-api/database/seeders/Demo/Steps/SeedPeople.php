<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Social\UpdateBusinessProfileAction;
use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\StaffRole;
use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\Account\UpdateBusinessProfileRequest;
use App\Models\User;
use Database\Seeders\Demo\Catalog\People;
use Database\Seeders\Demo\Content\DesignManifest;
use Database\Seeders\Demo\Content\DesignPersonaApplier;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One account per staff role, then the marketplace members: two well-known
 * logins (a buyer and a business seller) and a crowd of private and
 * business accounts with mixed languages and verification states.
 *
 * The first members play the design's sellers (names, avatars, logos,
 * covers and shop profiles from the design manifest); the rest get
 * generated names with avatars and covers taken from the same design.
 */
final class SeedPeople
{
    private const float BUSINESS_SHARE = 0.3;

    /** Design photos for members who do not play a design seller. */
    private const array AVATARS = ['avatar-farah', 'avatar-mark', 'avatar-jaden', 'avatar-chris', 'avatar-sara'];

    private const array COVERS = ['re-skyscrapers', 're-blue-building', 'cars-amg-grey', 're-office-corridor', 'cars-bmw-m3-studio'];

    private int $phoneSequence = 0;

    public function __construct(
        private readonly UpdateBusinessProfileAction $updateBusinessProfile,
        private readonly DemoMedia $media,
        private readonly DesignManifest $design,
        private readonly DesignPersonaApplier $personas,
    ) {}

    public function run(DemoContext $context): void
    {
        $passwordHash = Hash::make($context->options->password);

        DB::transaction(function () use ($context, $passwordHash): void {
            foreach (StaffRole::cases() as $role) {
                $context->staff[$role->value] = $this->createStaff($context, $role, $passwordHash);
            }

            for ($index = 0; $index < $context->options->users; $index++) {
                $context->members[] = $this->createMember($context, $index, $passwordHash);
            }
        });

        foreach ($context->members as $index => $member) {
            $this->dress($context, $member, $index);
        }
    }

    private function dress(DemoContext $context, User $member, int $index): void
    {
        $persona = $this->design->personaAt($index);

        if ($persona !== null) {
            $this->personas->apply($member, $persona);

            return;
        }

        $this->media->syncAvatar($member, self::AVATARS[$index % count(self::AVATARS)]);

        if ($member->isBusiness()) {
            $this->setUpBusiness($context, $member, $index);
        }
    }

    private function createStaff(DemoContext $context, StaffRole $role, string $passwordHash): User
    {
        $user = $this->createUser($context, [
            'full_name' => 'QBazaar ' . ucwords(str_replace('_', ' ', $role->value)),
            'email' => $context->email(str_replace('_', '-', $role->value)),
            'language' => Language::ENGLISH,
            'account_type' => AccountType::PRIVATE_INDIVIDUAL,
            'phone_verified' => true,
            'email_verified' => true,
        ], $passwordHash);

        $user->assignRole($role->value);

        return $user;
    }

    private function createMember(DemoContext $context, int $index, string $passwordHash): User
    {
        $random = $context->random;
        $persona = $this->design->personaAt($index);
        $isBusiness = match (true) {
            $persona !== null => $persona['kind'] === 'business',
            default => $random->chance(self::BUSINESS_SHARE),
        };
        $language = match (true) {
            $persona !== null => Language::from($persona['language']),
            default => $random->chance(0.6) ? Language::ARABIC : Language::ENGLISH,
        };
        $first = $random->pick(People::FIRST_NAMES);
        $family = $random->pick(People::FAMILY_NAMES);

        return $this->createUser($context, [
            'full_name' => $persona['name'][$language->value] ?? $first[$language->value] . ' ' . $family[$language->value],
            'email' => $context->email(match ($index) {
                0 => 'buyer',
                1 => 'seller',
                default => sprintf('member%02d', $index - 1),
            }),
            'language' => $language,
            'account_type' => $isBusiness ? AccountType::BUSINESS : AccountType::PRIVATE_INDIVIDUAL,
            'phone_verified' => $index < 2 || $random->chance(0.8),
            'email_verified' => $index < 2 || $random->chance(0.65),
            'last_login_at' => $context->clock->daysAgo($random->int(0, 20), $random->int(0, 600)),
            'created_at' => $context->clock->daysAgo($random->int(60, 400)),
        ], $passwordHash);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createUser(DemoContext $context, array $attributes, string $passwordHash): User
    {
        $user = new User;
        $user->forceFill([
            'phone' => $this->nextPhone(),
            'password' => $passwordHash,
            'status' => UserStatus::ACTIVE,
            ...$attributes,
        ])->save();

        return $user;
    }

    /**
     * Fake but well-formed Qatari mobile numbers: +974, a mobile prefix and
     * seven more digits, unique within the run.
     */
    private function nextPhone(): string
    {
        $sequence = $this->phoneSequence++;
        $prefix = People::MOBILE_PREFIXES[$sequence % count(People::MOBILE_PREFIXES)];

        return '+974' . $prefix . '9' . sprintf('%06d', $sequence);
    }

    private function setUpBusiness(DemoContext $context, User $owner, int $index): void
    {
        $business = $context->random->pick(People::BUSINESSES);
        $arabic = $owner->language === Language::ARABIC;

        ($this->updateBusinessProfile)($owner, [
            'business_name' => $arabic ? $business['ar'] : $business['en'],
            'about' => $arabic ? $business['about_ar'] : $business['about_en'],
            'contact_phone' => $owner->phone,
            'contact_email' => $owner->email,
            'address' => $arabic ? 'الدوحة، قطر' : 'Doha, Qatar',
            'opening_hours' => $this->openingHours($context),
        ]);

        $this->media->syncCover($owner, self::COVERS[$index % count(self::COVERS)]);
    }

    /**
     * @return list<array{day: string, closed: bool, open: string|null, close: string|null}>
     */
    private function openingHours(DemoContext $context): array
    {
        $opens = $context->random->pick(['08:00', '09:00', '10:00']);
        $closes = $context->random->pick(['21:00', '22:00', '23:30']);

        return array_map(static fn (string $day): array => $day === 'fri'
            ? ['day' => $day, 'closed' => false, 'open' => '16:00', 'close' => $closes]
            : ['day' => $day, 'closed' => false, 'open' => $opens, 'close' => $closes], UpdateBusinessProfileRequest::WEEKDAYS);
    }
}
