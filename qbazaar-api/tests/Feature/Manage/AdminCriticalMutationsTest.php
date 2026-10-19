<?php

declare(strict_types=1);

use App\Enums\PlatformSetting;
use App\Enums\SettlementStatus;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\actingAs;

use Tests\Concerns\ManagesMoney;

/*
 * Mutation guards for the admin actions that decide who may do what or that
 * move money (AD-17.16). Each one sends the same request as a role that must
 * be refused and, where the action is cheap to complete, as one that must get
 * through, and reads the stored state after both. Dropping a route's
 * permission check, or breaking the action behind it, fails a test here.
 */
uses(RefreshDatabase::class, ManagesMoney::class);

beforeEach(function (): void {
    $this->withoutVite();
    config([
        'scout.driver' => null,
        'qbazaar.web_url' => 'https://web.test',
        'qbazaar.commission.rate' => '5.00',
    ]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->customer = User::factory()->create();
});

function mutationActor(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

/**
 * Every platform setting at its default, as the settings form submits them.
 *
 * @param array<string, int|string> $overrides
 * @return array<string, int|string>
 */
function mutationSettings(array $overrides = []): array
{
    $values = [];

    foreach (PlatformSetting::cases() as $setting) {
        $values[$setting->value] = $setting->definition()->defaultValue();
    }

    return [...$values, ...$overrides];
}

it('hands out staff roles only to holders of roles.manage', function (): void {
    $payload = ['roles' => ['moderator']];

    actingAs(mutationActor('moderator'))->post(route('admin.users.roles', $this->customer), $payload)->assertForbidden();
    expect($this->customer->fresh()?->getRoleNames()->all())->toBe([]);

    actingAs(mutationActor('super_admin'))->post(route('admin.users.roles', $this->customer), $payload)->assertRedirect();
    expect($this->customer->fresh()?->getRoleNames()->all())->toBe(['moderator']);
});

it('issues an impersonation token only to holders of users.impersonate', function (): void {
    $payload = ['reason' => 'Reproducing a checkout bug reported in ticket 42'];

    actingAs(mutationActor('moderator'))->post(route('admin.users.impersonate', $this->customer), $payload)->assertForbidden();
    expect(PersonalAccessToken::query()->count())->toBe(0);

    actingAs(mutationActor('super_admin'))->post(route('admin.users.impersonate', $this->customer), $payload)->assertRedirect();
    expect(PersonalAccessToken::query()->where('tokenable_id', $this->customer->id)->count())->toBe(1);
});

it('changes the commission rate only for holders of settings.manage', function (string $role): void {
    $settings = app(SettingsService::class);
    $payload = mutationSettings(['commission_rate' => '7.25']);

    actingAs(mutationActor($role))->put(route('admin.settings.update'), $payload)->assertForbidden();
    expect($settings->decimal(PlatformSetting::COMMISSION_RATE))->toBe('5.00');

    actingAs(mutationActor('super_admin'))->put(route('admin.settings.update'), $payload)->assertRedirect();
    expect($settings->decimal(PlatformSetting::COMMISSION_RATE))->toBe('7.25');
})->with(['moderator', 'support']);

it('leaves a settlement pending when staff who may only view finance try to decide it', function (string $action, array $payload): void {
    $settlement = $this->settlementOf($this->customer, SettlementStatus::PENDING);
    $viewer = mutationActor('moderator');
    $viewer->givePermissionTo('finance.view');

    actingAs($viewer)->post(route('admin.finance.settlements.' . $action, $settlement), $payload)->assertForbidden();

    expect($settlement->refresh()->status)->toBe(SettlementStatus::PENDING)
        ->and($settlement->reviewed_by)->toBeNull();
})->with([
    'approve' => ['approve', []],
    'reject' => ['reject', ['reason' => 'No such transfer on the statement']],
]);
