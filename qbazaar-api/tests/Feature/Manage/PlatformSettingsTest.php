<?php

declare(strict_types=1);

use App\Enums\PlatformSetting;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

function validSettingsPayload(array $overrides = []): array
{
    $defaults = [];

    foreach (PlatformSetting::cases() as $setting) {
        $defaults[$setting->value] = $setting->definition()->defaultValue();
    }

    return [
        ...$defaults,
        'commission_rate' => (string) config('qbazaar.commission.rate'),
        'commission_debt_ceiling' => '750.50',
        'settlement_deadline_days' => 21,
        'ad_expiry_warning_days' => 5,
        'ad_max_images' => 20,
        'ad_daily_publish_limit' => 10,
        'offer_counter_rounds_per_side' => 1,
        'order_dispute_window_hours' => (int) config('qbazaar.orders.dispute_window_hours'),
        'escrow_auto_release_days' => (int) config('qbazaar.orders.escrow_auto_release_days'),
        ...$overrides,
    ];
}

it('shows the settings form with the config defaults', function (): void {
    config(['qbazaar.commission.debt_ceiling' => '500.00']);

    actingAs($this->admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee(__('admin.settings.fields.commission_debt_ceiling.label'))
        ->assertSee('value="500.00"', false)
        ->assertSee('value="3"', false);
});

it('restricts the settings page to settings.manage', function (string $role): void {
    $staff = User::factory()->create();
    $staff->assignRole($role);

    actingAs($staff);

    $this->get('/admin/settings')->assertForbidden();
    $this->put('/admin/settings', validSettingsPayload())->assertForbidden();

    expect(Setting::query()->count())->toBe(0);
})->with(['moderator', 'support']);

it('opens the settings page to any staff member granted settings.manage', function (): void {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');
    $moderator->givePermissionTo('settings.manage');

    actingAs($moderator)->get('/admin/settings')->assertOk();
    actingAs($moderator)->put('/admin/settings', validSettingsPayload())->assertRedirect('/admin/settings');

    expect(app(SettingsService::class)->integer(PlatformSetting::AD_EXPIRY_WARNING_DAYS))->toBe(5);
});

it('saves the settings and records who changed what', function (): void {
    actingAs($this->admin)
        ->put('/admin/settings', validSettingsPayload())
        ->assertRedirect('/admin/settings')
        ->assertSessionHas('status', __('admin.settings.saved'));

    $settings = app(SettingsService::class);

    expect($settings->decimal(PlatformSetting::COMMISSION_DEBT_CEILING))->toBe('750.50')
        ->and($settings->integer(PlatformSetting::SETTLEMENT_DEADLINE_DAYS))->toBe(21)
        ->and($settings->integer(PlatformSetting::AD_EXPIRY_WARNING_DAYS))->toBe(5)
        ->and(Setting::query()->find('settlement_deadline_days')?->updated_by)->toBe($this->admin->id);

    $entry = Activity::query()->where('log_name', 'settings')
        ->where('properties->key', 'ad_expiry_warning_days')
        ->sole();

    expect($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->properties['old'])->toBe(3)
        ->and($entry->properties['new'])->toBe(5);
});

it('only writes and logs the values that changed', function (): void {
    actingAs($this->admin)->put('/admin/settings', validSettingsPayload([
        'commission_debt_ceiling' => (string) config('qbazaar.commission.debt_ceiling'),
        'settlement_deadline_days' => (int) config('qbazaar.commission.settlement_deadline_days'),
    ]));

    expect(Setting::query()->pluck('key')->all())->toBe(['ad_expiry_warning_days'])
        ->and(Activity::query()->where('log_name', 'settings')->count())->toBe(1);
});

it('rejects out-of-range values', function (string $field, mixed $value): void {
    actingAs($this->admin)
        ->from('/admin/settings')
        ->put('/admin/settings', validSettingsPayload([$field => $value]))
        ->assertRedirect('/admin/settings')
        ->assertSessionHasErrors($field);

    expect(Setting::query()->count())->toBe(0);
})->with([
    'negative rate' => ['commission_rate', '-0.01'],
    'rate above 100' => ['commission_rate', '100.01'],
    'rate with three decimals' => ['commission_rate', '2.555'],
    'negative ceiling' => ['commission_debt_ceiling', '-1'],
    'ceiling with three decimals' => ['commission_debt_ceiling', '10.555'],
    'ceiling not a number' => ['commission_debt_ceiling', 'abc'],
    'deadline zero' => ['settlement_deadline_days', 0],
    'deadline above 90' => ['settlement_deadline_days', 91],
    'deadline fraction' => ['settlement_deadline_days', '2.5'],
    'warning zero' => ['ad_expiry_warning_days', 0],
    'warning above 30' => ['ad_expiry_warning_days', 31],
    'warning missing' => ['ad_expiry_warning_days', null],
    'images zero' => ['ad_max_images', 0],
    'images above 20' => ['ad_max_images', 21],
    'daily limit zero' => ['ad_daily_publish_limit', 0],
    'counter rounds negative' => ['offer_counter_rounds_per_side', -1],
    'counter rounds above 5' => ['offer_counter_rounds_per_side', 6],
    'promotion price below 1' => ['promotion_premium_price', '0.99'],
    'promotion duration zero' => ['promotion_highlight_days', 0],
    'promotion duration above 90' => ['promotion_gallery_days', 91],
    'dispute window zero' => ['order_dispute_window_hours', 0],
    'dispute window above 30 days' => ['order_dispute_window_hours', 721],
    'auto-release zero' => ['escrow_auto_release_days', 0],
    'auto-release above 60' => ['escrow_auto_release_days', 61],
]);

it('serves reads from the cache and refreshes it after a write', function (): void {
    $settings = app(SettingsService::class);
    $settings->all();

    DB::enableQueryLog();
    $settings->integer(PlatformSetting::AD_EXPIRY_WARNING_DAYS);
    $settings->decimal(PlatformSetting::COMMISSION_DEBT_CEILING);
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();

    $settings->put(PlatformSetting::AD_EXPIRY_WARNING_DAYS, 7, $this->admin);

    expect($settings->integer(PlatformSetting::AD_EXPIRY_WARNING_DAYS))->toBe(7);
});

it('falls back to the config default when nothing is stored', function (): void {
    config(['qbazaar.commission.settlement_deadline_days' => 30]);

    expect(app(SettingsService::class)->integer(PlatformSetting::SETTLEMENT_DEADLINE_DAYS))->toBe(30);
});
