<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

use App\Actions\Social\UpdateBusinessProfileAction;
use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\Demo\DemoMedia;
use Illuminate\Support\Carbon;

/**
 * Gives a demo account the identity of one of the design's sellers: its
 * name, avatar or logo, and for businesses the profile and cover. Safe to
 * repeat; whatever already matches the design is not touched.
 *
 * The account's language, e-mail and password are never changed.
 */
final class DesignPersonaApplier
{
    public function __construct(
        private readonly UpdateBusinessProfileAction $updateBusinessProfile,
        private readonly DemoMedia $media,
    ) {}

    /**
     * @param array<string, mixed> $persona
     * @return bool whether anything on the account changed
     */
    public function apply(User $user, array $persona): bool
    {
        $language = $user->language->value;
        $accountChanged = $this->syncAccount($user, $persona, $language);
        $avatarChanged = $this->media->syncAvatar($user, $persona['avatar']);
        $businessChanged = $user->isBusiness() && isset($persona['business'])
            && $this->syncBusiness($user, $persona['business'], $persona['about'][$language] ?? null, $language);

        return $accountChanged || $avatarChanged || $businessChanged;
    }

    /**
     * @param array<string, mixed> $persona
     */
    private function syncAccount(User $user, array $persona, string $language): bool
    {
        $user->full_name = $persona['name'][$language];

        if ($persona['kind'] === 'business') {
            $user->account_type = AccountType::BUSINESS;
        }

        if ($persona['member_since'] !== null && ! $user->created_at->isSameDay(Carbon::parse($persona['member_since']))) {
            $user->created_at = Carbon::parse($persona['member_since']);
        }

        $changed = $user->isDirty();
        $user->save();

        return $changed;
    }

    /**
     * @param array<string, mixed> $business
     */
    private function syncBusiness(User $user, array $business, ?string $about, string $language): bool
    {
        $profile = ($this->updateBusinessProfile)($user, array_filter([
            'business_name' => $business['business_name'][$language],
            'about' => $about,
            'legal_name' => $business['legal_name'],
            'commercial_registration_number' => $business['commercial_registration_number'],
            'contact_phone' => $business['contact_phone'] ?? $user->phone,
            'contact_email' => $business['contact_email'] ?? $user->email,
            'website' => $business['website'],
            'address' => $business['address'][$language],
            'opening_hours' => $business['opening_hours'],
        ], static fn (mixed $value): bool => $value !== null));

        $coverChanged = $this->media->syncCover($user, $business['cover']);

        return $profile->wasChanged() || $profile->wasRecentlyCreated || $coverChanged;
    }
}
