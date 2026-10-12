<?php

declare(strict_types=1);

use App\Enums\PlatformSetting;
use App\Enums\SettingGroup;
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

/**
 * @return array<string, mixed>
 */
function promotionSettingDefaults(): array
{
    $defaults = [];

    foreach (PlatformSetting::cases() as $setting) {
        if ($setting->definition()->group === SettingGroup::PROMOTIONS) {
            $defaults[$setting->value] = $setting->definition()->defaultValue();
        }
    }

    return $defaults;
}

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
        ...promotionSettingDefaults(),
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

/**
 * @return array{DOMXPath, DOMDocument}
 */
function settingsPageXpath(string $html): array
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();

    return [new DOMXPath($dom), $dom];
}

it('renders the category commission card inside the commission panel only', function (): void {
    CategoryCommissionRate::query()->create(['category_id' => $this->category->id, 'rate' => '3.50']);

    [$xpath] = settingsPageXpath(actingAs($this->admin)->get('/admin/settings')->assertOk()->getContent());

    $panels = $xpath->query('//*[@role="tabpanel"]');
    $withCard = [];

    foreach ($panels as $panel) {
        if ($xpath->query('.//select[@id="commission_category_id"]', $panel)->length > 0) {
            $withCard[] = $panel->getAttribute('id');
        }
    }

    expect($withCard)->toBe(['settings-panel-commission'])
        ->and($xpath->query('//select[@id="commission_category_id"]')->length)->toBe(1);
});

it('never nests a form inside another form on the settings page', function (): void {
    CategoryCommissionRate::query()->create(['category_id' => $this->category->id, 'rate' => '3.50']);

    [$xpath] = settingsPageXpath(actingAs($this->admin)->get('/admin/settings')->getContent());

    expect($xpath->query('//form//form')->length)->toBe(0)
        ->and($xpath->query('//form[@id="commission-rate-form"]')->length)->toBe(1)
        ->and($xpath->query('//*[@form="commission-rate-form"]')->length)->toBeGreaterThanOrEqual(3)
        ->and($xpath->query('//button[@form="commission-rate-remove-' . $this->category->id . '"]')->length)->toBe(1);
});

it('keeps the settings Save and the category Save separate', function (): void {
    [$xpath] = settingsPageXpath(actingAs($this->admin)->get('/admin/settings')->getContent());

    $mainForm = '//form[@action="' . route('admin.settings.update') . '"]';

    expect($xpath->query($mainForm . '//button[not(@form) and not(@role="tab")]')->length)->toBe(1)
        ->and($xpath->query('//button[@form="commission-rate-form"]')->length)->toBe(1)
        ->and($xpath->query($mainForm . '//*[@id="commission_category_id"]/@form')->item(0)?->nodeValue)->toBe('commission-rate-form');
});

it('uses no fixed widths wider than a phone on the settings page', function (): void {
    $html = actingAs($this->admin)->get('/admin/settings')->getContent();

    expect($html)->not->toMatch('/\b(?:min-w|w)-\[(?:[4-9]\d{2}|\d{4,})px\]/')
        ->and($html)->not->toMatch('/style="[^"]*width:\s*\d{3,}px/');
});

it('saves the main settings and a category rate independently', function (): void {
    $payload = [];
    foreach (PlatformSetting::cases() as $setting) {
        $payload[$setting->value] = $setting->definition()->defaultValue();
    }
    $payload['commission_rate'] = '6.00';

    actingAs($this->admin)->put('/admin/settings', $payload)->assertRedirect('/admin/settings');
    actingAs($this->admin)->post('/admin/settings/commission-rates', ['category_id' => $this->category->id, 'rate' => '2.5'])->assertRedirect('/admin/settings');

    expect(app(SettingsService::class)->decimal(PlatformSetting::COMMISSION_RATE))->toBe('6.00')
        ->and(CategoryCommissionRate::query()->findOrFail($this->category->id)->rate)->toBe('2.50');
});

it('marks the commission tab when the category rate form fails', function (): void {
    actingAs($this->admin)->from('/admin/settings')
        ->post('/admin/settings/commission-rates', ['category_id' => $this->category->id, 'rate' => '500'])
        ->assertSessionHasErrors('rate');

    $html = actingAs($this->admin)->get('/admin/settings')->getContent();

    expect($html)->toMatch('/id="settings-tab-commission"[^>]*data-invalid/');
});
