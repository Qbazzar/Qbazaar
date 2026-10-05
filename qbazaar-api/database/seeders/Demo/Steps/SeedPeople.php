<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Social\ReplaceBusinessCoverAction;
use App\Actions\Social\UpdateBusinessProfileAction;
use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\StaffRole;
use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\Account\UpdateBusinessProfileRequest;
use App\Models\User;
use Database\Seeders\Demo\Catalog\People;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One account per staff role, then the marketplace members: two well-known
 * logins (a buyer and a business seller) and a crowd of private and
 * business accounts with mixed languages and verification states.
 */
final class SeedPeople
{
    private const float BUSINESS_SHARE = 0.3;

    private int $phoneSequence = 0;

    public function __construct(
        private readonly UpdateBusinessProfileAction $updateBusinessProfile,
        private readonly ReplaceBusinessCoverAction $replaceCover,
        private readonly DemoMedia $media,
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

        foreach ($context->members as $member) {
            if ($member->isBusiness()) {
                $this->setUpBusiness($context, $member);
            }
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
        $isBusiness = match ($index) {
            0 => false,
            1 => true,
            default => $random->chance(self::BUSINESS_SHARE),
        };
        $language = $index === 0 || $random->chance(0.6) ? Language::ARABIC : Language::ENGLISH;
        $first = $random->pick(People::FIRST_NAMES);
        $family = $random->pick(People::FAMILY_NAMES);

        return $this->createUser($context, [
            'full_name' => $first[$language->value] . ' ' . $family[$language->value],
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

    private function setUpBusiness(DemoContext $context, User $owner): void
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

        $this->replaceCover->upload($owner, $this->media->coverPhoto($business['en']));
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
