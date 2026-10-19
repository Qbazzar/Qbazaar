<?php

declare(strict_types=1);

use App\Enums\PlatformSetting;
use App\Exceptions\ErrorCode;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\ManagesMoney;
use Tests\Concerns\PlacesOrders;

/*
 * The payment settings an admin edits on /admin/settings (AD-17.7) reach the
 * money flows that read them: the next order's commission, the debt ceiling
 * that stops a seller taking orders, and the promotion catalogue.
 */
uses(RefreshDatabase::class, CreatesAds::class, ManagesMoney::class, PlacesOrders::class);

beforeEach(function (): void {
    $this->withoutVite();
    config([
        'scout.driver' => null,
        'qbazaar.commission.rate' => '5.00',
        'qbazaar.commission.debt_ceiling' => '100.00',
    ]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
});

/**
 * Every platform setting at its default, with the admin's changes on top.
 *
 * @param array<string, int|string> $changes
 * @return array<string, int|string>
 */
function paymentSettingsForm(array $changes): array
{
    $values = [];

    foreach (PlatformSetting::cases() as $setting) {
        $values[$setting->value] = $setting->definition()->defaultValue();
    }

    return [...$values, ...$changes];
}

/**
 * @param array<string, int|string> $changes
 */
function savePaymentSettings(User $admin, array $changes): void
{
    test()->actingAs($admin, 'web')
        ->put(route('admin.settings.update'), paymentSettingsForm($changes))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
}

it('applies a commission rate changed in the admin panel to the next order and leaves earlier orders alone', function (): void {
    $earlier = $this->placeOrder($this->seller, $this->buyer, '200.00');

    savePaymentSettings($this->admin, ['commission_rate' => '7.50']);

    $next = $this->placeOrder($this->seller, User::factory()->create(), '200.00');

    expect($earlier->fresh()?->commission_rate)->toBe('5.00')
        ->and($earlier->fresh()?->commission_amount)->toBe('10.00')
        ->and($next->commission_rate)->toBe('7.50')
        ->and($next->commission_amount)->toBe('15.00');
});

it('stops a seller at a debt ceiling lowered in the admin panel', function (): void {
    $this->owesCommission($this->seller, '40.00');
    $offer = $this->pendingOffer($this->seller, $this->buyer);

    savePaymentSettings($this->admin, ['commission_debt_ceiling' => '40.00']);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/offers/' . $offer->id . '/accept')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::ORDER_SELLER_DEBT_CEILING->value);

    expect(Order::query()->where('source_id', $offer->id)->exists())->toBeFalse();
});

it('prices the promotion catalogue from the admin panel', function (): void {
    savePaymentSettings($this->admin, ['promotion_highlight_price' => '33.00', 'promotion_highlight_days' => 9]);

    $this->getJson('/api/v1/promotions')
        ->assertOk()
        ->assertJsonPath('data.0', ['type' => 'highlight', 'price' => '33.00', 'currency' => 'QAR', 'duration_days' => 9]);
});
