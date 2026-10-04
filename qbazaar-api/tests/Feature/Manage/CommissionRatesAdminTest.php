<?php

declare(strict_types=1);

use App\Enums\PlatformSetting;
use App\Models\Category;
use App\Models\CategoryCommissionRate;
use App\Models\User;
use App\Services\Orders\CommissionRates;
use App\Services\Settings\SettingsService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, CategorySeeder::class]);
    config(['qbazaar.commission.rate' => '5.00']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->category = Category::query()->whereNull('parent_id')->whereHas('children')->firstOrFail();
});

it('changes the general commission rate from the settings page and logs who did it', function (): void {
    actingAs($this->admin)->put('/admin/settings', [
        'commission_rate' => '7.25',
        'commission_debt_ceiling' => '500.00',
        'settlement_deadline_days' => 14,
        'ad_expiry_warning_days' => 3,
        'ad_max_images' => (int) config('qbazaar.ads.max_images'),
        'ad_daily_publish_limit' => (int) config('qbazaar.ads.daily_publish_limit_per_user'),
        'offer_counter_rounds_per_side' => (int) config('qbazaar.offers.counter_rounds_per_side'),
        'order_dispute_window_hours' => (int) config('qbazaar.orders.dispute_window_hours'),
        'escrow_auto_release_days' => (int) config('qbazaar.orders.escrow_auto_release_days'),
    ])->assertRedirect('/admin/settings');

    $entry = Activity::query()->where('log_name', 'settings')->where('properties->key', 'commission_rate')->sole();

    expect(app(SettingsService::class)->decimal(PlatformSetting::COMMISSION_RATE))->toBe('7.25')
        ->and(app(CommissionRates::class)->rateFor($this->category->id))->toBe('7.25')
        ->and($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->properties['old'])->toBe('5.00')
        ->and($entry->properties['new'])->toBe('7.25');
});

it('sets, lists and removes a category override, logging each change', function (): void {
    actingAs($this->admin)
        ->post('/admin/settings/commission-rates', ['category_id' => $this->category->id, 'rate' => '3.5'])
        ->assertRedirect('/admin/settings')
        ->assertSessionHas('status', __('admin.commission_rates.saved'));

    $child = $this->category->children()->firstOrFail();
    expect(CategoryCommissionRate::query()->findOrFail($this->category->id)->rate)->toBe('3.50')
        ->and(app(CommissionRates::class)->rateFor($child->id))->toBe('3.50');

    actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('3.50%');

    actingAs($this->admin)
        ->delete('/admin/settings/commission-rates/' . $this->category->id)
        ->assertRedirect('/admin/settings');

    expect(CategoryCommissionRate::query()->count())->toBe(0)
        ->and(app(CommissionRates::class)->rateFor($child->id))->toBe('5.00');

    $log = Activity::query()->where('log_name', 'settings')->where('properties->key', 'category_commission_rate')->orderBy('id')->get();
    expect($log)->toHaveCount(2)
        ->and($log->pluck('causer_id')->unique()->all())->toBe([$this->admin->id])
        ->and($log[0]->properties['new'])->toBe('3.50')
        ->and($log[1]->properties['old'])->toBe('3.50')
        ->and($log[1]->properties['new'])->toBeNull();
});

it('validates a category override', function (array $payload, string $field): void {
    actingAs($this->admin)
        ->from('/admin/settings')
        ->post('/admin/settings/commission-rates', $payload)
        ->assertSessionHasErrors($field);

    expect(CategoryCommissionRate::query()->count())->toBe(0);
})->with([
    'unknown category' => [['category_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ', 'rate' => '2.00'], 'category_id'],
    'rate above 100' => [['rate' => '101'], 'rate'],
    'negative rate' => [['rate' => '-1'], 'rate'],
    'three decimals' => [['rate' => '1.005'], 'rate'],
]);

it('keeps category overrides to settings.manage', function (): void {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    actingAs($moderator)->post('/admin/settings/commission-rates', ['category_id' => $this->category->id, 'rate' => '1.00'])->assertForbidden();
    actingAs($moderator)->delete('/admin/settings/commission-rates/' . $this->category->id)->assertForbidden();

    expect(CategoryCommissionRate::query()->count())->toBe(0);
});
