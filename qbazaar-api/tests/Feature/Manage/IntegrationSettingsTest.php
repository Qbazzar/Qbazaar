<?php

declare(strict_types=1);

use App\Enums\IntegrationGroup;
use App\Enums\IntegrationSetting;
use App\Enums\PlatformSetting;
use App\Models\IntegrationSettingRow;
use App\Models\User;
use App\Services\Settings\IntegrationConfigApplier;
use App\Services\Settings\IntegrationSettings;
use App\Services\Settings\IntegrationTester;
use App\Services\Settings\WorkerRestarter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const SECRET_KEY_VALUE = 're_live_SuperSecretKey1234';

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    // Workers are restarted through Horizon; never touch it from tests.
    $this->restarter = $this->mock(WorkerRestarter::class)->shouldIgnoreMissing();
});

/**
 * @param array<string, mixed> $integrations
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function settingsWith(array $integrations = [], array $overrides = []): array
{
    $platform = [];

    foreach (PlatformSetting::cases() as $setting) {
        $platform[$setting->value] = $setting->definition()->defaultValue();
    }

    return [...$platform, 'integrations' => $integrations, ...$overrides];
}

function integrations(): IntegrationSettings
{
    return app(IntegrationSettings::class);
}

it('shows every integration tab to a super admin', function (): void {
    $response = actingAs($this->admin)->get('/admin/settings')->assertOk();

    foreach (IntegrationGroup::cases() as $group) {
        $response->assertSee('id="settings-tab-' . $group->value . '"', false)
            ->assertSee(__('admin.integrations.groups.' . $group->value));
    }

    foreach (IntegrationSetting::cases() as $setting) {
        $response->assertSee('name="integrations[' . $setting->value . ']"', false);
    }
});

it('shows only the contact tab to staff granted settings.manage', function (): void {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');
    $moderator->givePermissionTo('settings.manage');

    $response = actingAs($moderator)->get('/admin/settings')->assertOk();

    $response->assertSee('id="settings-tab-contact"', false);

    foreach (IntegrationGroup::cases() as $group) {
        if ($group !== IntegrationGroup::CONTACT) {
            $response->assertDontSee('id="settings-tab-' . $group->value . '"', false);
        }
    }

    $response->assertDontSee('name="integrations[email_api_key]"', false);
});

it('ignores credential input from staff who lack integrations.manage', function (): void {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');
    $moderator->givePermissionTo('settings.manage');

    actingAs($moderator)->put('/admin/settings', settingsWith([
        'email_api_key' => SECRET_KEY_VALUE,
        'contact_phone' => '+97444000000',
    ]))->assertRedirect('/admin/settings');

    expect(integrations()->get(IntegrationSetting::EMAIL_API_KEY))->toBeNull()
        ->and(integrations()->get(IntegrationSetting::CONTACT_PHONE))->toBe('+97444000000');
});

it('forbids the test actions without integrations.manage', function (string $role): void {
    $staff = User::factory()->create();
    $staff->assignRole($role);
    $staff->givePermissionTo('settings.manage');

    actingAs($staff)->post('/admin/settings/test-email', ['test_email' => 'a@example.com'])->assertForbidden();
    actingAs($staff)->post('/admin/settings/test-sms', ['test_phone' => '+97455000000'])->assertForbidden();
})->with(['moderator', 'support']);

it('stores secrets encrypted at rest', function (): void {
    actingAs($this->admin)->put('/admin/settings', settingsWith(['email_api_key' => SECRET_KEY_VALUE]))
        ->assertRedirect('/admin/settings');

    $raw = DB::table('integration_settings')->where('key', 'email_api_key')->value('value');

    expect($raw)->not->toContain(SECRET_KEY_VALUE)
        ->and(Crypt::decryptString($raw))->toBe(SECRET_KEY_VALUE)
        ->and(integrations()->get(IntegrationSetting::EMAIL_API_KEY))->toBe(SECRET_KEY_VALUE);
});

it('never renders a stored secret and shows only a masked hint', function (): void {
    integrations()->put(IntegrationSetting::EMAIL_API_KEY, SECRET_KEY_VALUE, $this->admin);
    integrations()->put(IntegrationSetting::PUSH_SERVICE_ACCOUNT, '{"project_id":"p","client_email":"x@p.iam"}', $this->admin);

    $html = actingAs($this->admin)->get('/admin/settings')->assertOk()->getContent();

    expect($html)->not->toContain(SECRET_KEY_VALUE)
        ->and($html)->not->toContain('x@p.iam')
        ->and($html)->toContain('•••• 1234');
});

it('keeps a secret on an empty submit and removes it only on an explicit clear', function (): void {
    integrations()->put(IntegrationSetting::EMAIL_API_KEY, SECRET_KEY_VALUE, $this->admin);

    actingAs($this->admin)->put('/admin/settings', settingsWith(['email_api_key' => '']))->assertRedirect('/admin/settings');
    expect(integrations()->get(IntegrationSetting::EMAIL_API_KEY))->toBe(SECRET_KEY_VALUE);

    actingAs($this->admin)->put('/admin/settings', settingsWith(['email_api_key' => ''], ['integrations_clear' => ['email_api_key' => '1']]))
        ->assertRedirect('/admin/settings');
    expect(integrations()->get(IntegrationSetting::EMAIL_API_KEY))->toBeNull()
        ->and(IntegrationSettingRow::query()->count())->toBe(0);
});

it('does not flash submitted secrets back after a validation error', function (): void {
    actingAs($this->admin)->from('/admin/settings')->put('/admin/settings', settingsWith([
        'email_api_key' => SECRET_KEY_VALUE,
        'contact_phone' => 'not-a-phone',
    ]))->assertSessionHasErrors('integrations.contact_phone');

    expect(json_encode(session()->all()))->not->toContain(SECRET_KEY_VALUE);
});

it('audits integration changes by key without any value', function (): void {
    actingAs($this->admin)->put('/admin/settings', settingsWith([
        'email_api_key' => SECRET_KEY_VALUE,
        'contact_phone' => '+97444000000',
    ]));

    $logs = Activity::query()->where('description', 'Integration setting updated')->get();

    expect($logs->pluck('properties')->map->toArray()->all())->toContain(['key' => 'email_api_key'], ['key' => 'contact_phone']);
    expect(json_encode($logs->toArray()))->not->toContain(SECRET_KEY_VALUE)->not->toContain('97444000000');
});

it('validates contact, key and credential formats', function (array $input, string $field): void {
    actingAs($this->admin)->from('/admin/settings')->put('/admin/settings', settingsWith($input))
        ->assertSessionHasErrors('integrations.' . $field);

    $html = actingAs($this->admin)->get('/admin/settings')->getContent();
    expect($html)->toContain('data-invalid');
})->with([
    'bad support email' => [['contact_support_email' => 'nope'], 'contact_support_email'],
    'phone without plus' => [['contact_phone' => '97444000000'], 'contact_phone'],
    'whatsapp too short' => [['contact_whatsapp' => '+123'], 'contact_whatsapp'],
    'http social link' => [['social_instagram' => 'http://instagram.com/q'], 'social_instagram'],
    'not a url' => [['social_x' => 'x.com/q'], 'social_x'],
    'bad ga4 id' => [['google_ga4_measurement_id' => 'UA-123'], 'google_ga4_measurement_id'],
    'service account without client_email' => [['push_service_account' => '{"project_id":"p"}'], 'push_service_account'],
    'service account not json' => [['push_service_account' => 'hello'], 'push_service_account'],
    'apple key not pem' => [['apple_private_key' => 'abc'], 'apple_private_key'],
    'unknown payment provider' => [['payment_provider' => 'bitcoin'], 'payment_provider'],
    'bad twilio sid' => [['sms_account_sid' => 'nope'], 'sms_account_sid'],
]);

it('accepts well-formed values for every group', function (): void {
    $pem = "-----BEGIN PRIVATE KEY-----\nMIGTAgEA\n-----END PRIVATE KEY-----";

    actingAs($this->admin)->put('/admin/settings', settingsWith([
        'contact_support_email' => 'help@qbazaar.qa',
        'social_youtube' => 'https://youtube.com/@qbazaar',
        'google_ga4_measurement_id' => 'G-ABCD123456',
        'apple_private_key' => $pem,
        'push_service_account' => '{"project_id":"p","client_email":"x@p.iam.gserviceaccount.com"}',
        'sms_account_sid' => 'AC' . str_repeat('a', 32),
        'payment_provider' => 'tap',
        'payment_mode' => 'live',
    ]))->assertSessionHasNoErrors()->assertRedirect('/admin/settings');

    expect(integrations()->get(IntegrationSetting::PAYMENT_MODE))->toBe('live')
        ->and(integrations()->get(IntegrationSetting::APPLE_PRIVATE_KEY))->toBe($pem);
});

it('restarts the workers only when a credential changed', function (): void {
    $this->restarter->shouldReceive('restart')->once();

    actingAs($this->admin)->put('/admin/settings', settingsWith(['contact_phone' => '+97444000000']));
    actingAs($this->admin)->put('/admin/settings', settingsWith(['sms_auth_token' => 'token-value-123456']));
});

it('serves only the public fields on the public endpoint', function (): void {
    foreach ([
        IntegrationSetting::CONTACT_SUPPORT_EMAIL->value => 'help@qbazaar.qa',
        IntegrationSetting::SOCIAL_INSTAGRAM->value => 'https://instagram.com/qbazaar',
        IntegrationSetting::GOOGLE_GA4_MEASUREMENT_ID->value => 'G-ABCD123456',
        IntegrationSetting::TURNSTILE_SITE_KEY->value => '0x4AAAAsite',
    ] as $key => $value) {
        integrations()->put(IntegrationSetting::from($key), $value, $this->admin);
    }
    integrations()->put(IntegrationSetting::EMAIL_API_KEY, SECRET_KEY_VALUE, $this->admin);
    integrations()->put(IntegrationSetting::TURNSTILE_SECRET_KEY, 'turnstile-secret-9999', $this->admin);
    integrations()->put(IntegrationSetting::PAYMENT_MERCHANT_ID, 'merchant-77', $this->admin);

    $response = $this->getJson('/api/v1/settings/public')->assertOk();

    $response->assertJsonPath('data.contact.support_email', 'help@qbazaar.qa')
        ->assertJsonPath('data.social.instagram', 'https://instagram.com/qbazaar')
        ->assertJsonPath('data.analytics.ga4_measurement_id', 'G-ABCD123456')
        ->assertJsonPath('data.antibot.turnstile_site_key', '0x4AAAAsite');

    expect($response->headers->get('Cache-Control'))->toContain('max-age=300');

    $body = $response->getContent();
    expect($body)->not->toContain(SECRET_KEY_VALUE)
        ->not->toContain('turnstile-secret-9999')
        ->not->toContain('merchant-77');
});

it('refreshes the cached public payload after a save', function (): void {
    $this->getJson('/api/v1/settings/public')->assertJsonPath('data.contact.phone', null);

    actingAs($this->admin)->put('/admin/settings', settingsWith(['contact_phone' => '+97444000000']));

    $this->getJson('/api/v1/settings/public')->assertJsonPath('data.contact.phone', '+97444000000');
});

it('overrides config from stored settings and falls back to env when empty', function (): void {
    config([
        'services.twilio.sid' => 'ACenvfallback',
        'services.twilio.token' => 'env-token',
        'services.resend.key' => 'env-resend',
        'services.google.client_ids' => 'env-client.apps.googleusercontent.com',
        'mail.default' => 'log',
    ]);

    integrations()->put(IntegrationSetting::EMAIL_API_KEY, 'db-resend', $this->admin);
    integrations()->put(IntegrationSetting::EMAIL_ENABLED, '1', $this->admin);
    integrations()->put(IntegrationSetting::GOOGLE_CLIENT_ID, 'db-client.apps.googleusercontent.com', $this->admin);

    app(IntegrationConfigApplier::class)->apply();

    expect(config('services.resend.key'))->toBe('db-resend')
        ->and(config('mail.default'))->toBe('resend')
        ->and(config('services.twilio.sid'))->toBe('ACenvfallback')
        ->and(config('services.twilio.token'))->toBe('env-token')
        ->and(config('services.google.client_ids'))->toBe('env-client.apps.googleusercontent.com,db-client.apps.googleusercontent.com');
});

it('does not change the mailer when email is not enabled', function (): void {
    config(['mail.default' => 'log']);
    integrations()->put(IntegrationSetting::EMAIL_API_KEY, 'db-resend', $this->admin);

    app(IntegrationConfigApplier::class)->apply();

    expect(config('mail.default'))->toBe('log');
});

it('reads a changed value after the cache is busted by a save', function (): void {
    integrations()->put(IntegrationSetting::PAYMENT_MODE, 'test', $this->admin);
    expect(integrations()->get(IntegrationSetting::PAYMENT_MODE))->toBe('test');

    integrations()->put(IntegrationSetting::PAYMENT_MODE, 'live', $this->admin);

    expect(integrations()->get(IntegrationSetting::PAYMENT_MODE))->toBe('live');
    Cache::flush();
});

it('sends a test email through the mailer and rate limits the action', function (): void {
    Mail::fake();

    foreach (range(1, 3) as $_) {
        actingAs($this->admin)->post('/admin/settings/test-email', ['test_email' => 'me@example.com'])
            ->assertRedirect('/admin/settings#email')
            ->assertSessionHas('status');
    }

    actingAs($this->admin)->post('/admin/settings/test-email', ['test_email' => 'me@example.com'])->assertStatus(429);

    Mail::assertSentCount(3);
});

it('validates the test email address', function (): void {
    Mail::fake();

    actingAs($this->admin)->from('/admin/settings')->post('/admin/settings/test-email', ['test_email' => 'nope'])
        ->assertSessionHasErrors('test_email');

    Mail::assertNothingSent();
});

it('sends a test SMS through the tester and rate limits the action', function (): void {
    $this->mock(IntegrationTester::class)->shouldReceive('sendSms')->with('+97455000000')->times(3);

    foreach (range(1, 3) as $_) {
        actingAs($this->admin)->post('/admin/settings/test-sms', ['test_phone' => '+97455000000'])
            ->assertRedirect('/admin/settings#sms')
            ->assertSessionHas('status');
    }

    actingAs($this->admin)->post('/admin/settings/test-sms', ['test_phone' => '+97455000000'])->assertStatus(429);
});

it('reports a failed test send without leaking the provider message', function (): void {
    $this->mock(IntegrationTester::class)->shouldReceive('sendSms')->andThrow(new RuntimeException('auth failed for ' . SECRET_KEY_VALUE));

    $this->followingRedirects();
    $response = actingAs($this->admin)->post('/admin/settings/test-sms', ['test_phone' => '+97455000000']);

    expect($response->getContent())->not->toContain(SECRET_KEY_VALUE)
        ->and($response->getContent())->toContain(e(__('admin.integrations.test_failed')));
});

it('lists the payment provider options and the read-only dashboard URLs', function (): void {
    $response = actingAs($this->admin)->get('/admin/settings')->assertOk();

    foreach (IntegrationSetting::PAYMENT_PROVIDERS as $provider) {
        $response->assertSee('value="' . $provider . '"', false);
    }

    $response->assertSee('/api/v1/payments/webhook', false)->assertSee('readonly', false);
});

it('resolves the native resend mailer once a key is stored', function (): void {
    integrations()->put(IntegrationSetting::EMAIL_API_KEY, 're_test_key_123456', $this->admin);
    app(IntegrationConfigApplier::class)->apply();

    expect(Mail::mailer('resend')->getSymfonyTransport()->__toString())->toStartWith('resend');
});
