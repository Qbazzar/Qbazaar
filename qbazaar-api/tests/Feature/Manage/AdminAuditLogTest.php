<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null, 'qbazaar.web_url' => 'https://web.test']);

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE])->assignRole('super_admin');
    $this->customer = User::factory()->create(['status' => UserStatus::ACTIVE]);
});

function adminEntries(): Builder
{
    return Activity::query()->where('log_name', AdminAuditLogger::LOG_NAME);
}

it('records an admin mutation with the acting admin as causer', function (): void {
    actingAs($this->admin)->post("/admin/users/{$this->customer->id}/suspend")->assertRedirect();

    $entry = adminEntries()->sole();

    expect($entry->event)->toBe('admin.users.suspend')
        ->and($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->subject_id)->toBe($this->customer->id);

    $statusChange = Activity::query()->where('log_name', 'user')->where('event', 'status_changed')->sole();

    expect($statusChange->causer_id)->toBe($this->admin->id);
});

it('keeps the owner as causer when users change their own records', function (): void {
    $this->customer->forceFill(['status' => UserStatus::DEACTIVATED])->save();

    expect(Activity::query()->where('event', 'status_changed')->sole()->causer_id)->toBe($this->customer->id);
});

it('does not record refused or invalid admin requests', function (): void {
    $peer = User::factory()->create(['status' => UserStatus::ACTIVE])->assignRole('super_admin');

    actingAs($this->admin)->post("/admin/users/{$peer->id}/suspend")->assertForbidden();
    actingAs($this->admin)->post("/admin/users/{$this->customer->id}/impersonate", ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect(adminEntries()->count())->toBe(0);
});

it('never stores submitted passwords in the audit log', function (): void {
    actingAs($this->admin)->put('/admin/profile', [
        'full_name' => 'New Name',
        'email' => $this->admin->email,
        'password' => 'a-brand-new-secret',
        'password_confirmation' => 'a-brand-new-secret',
    ])->assertRedirect();

    $input = adminEntries()->sole()->properties['input'];

    expect($input)->toHaveKey('full_name')
        ->not->toHaveKey('password')
        ->not->toHaveKey('password_confirmation')
        ->not->toHaveKey('_token');
});

it('records role changes with the previous and new roles', function (): void {
    $this->customer->assignRole('support');

    actingAs($this->admin)
        ->post("/admin/users/{$this->customer->id}/roles", ['roles' => ['moderator']])
        ->assertRedirect();

    $entry = adminEntries()->sole();

    expect($entry->event)->toBe('admin.users.roles_changed')
        ->and($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->properties['old'])->toBe(['support'])
        ->and($entry->properties['new'])->toBe(['moderator']);
});

it('issues a short-lived access token without a refresh token when impersonating', function (): void {
    Carbon::setTestNow('2026-09-30 10:00:00');

    $response = actingAs($this->admin)->post("/admin/users/{$this->customer->id}/impersonate", [
        'reason' => 'Reproducing a checkout bug reported in ticket 42',
    ]);

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_FRAGMENT), $fragment);

    expect($location)->toStartWith('https://web.test/impersonate#')
        ->and($fragment)->toHaveKeys(['access', 'expires_in', 'name'])
        ->not->toHaveKey('refresh')
        ->and((int) $fragment['expires_in'])->toBe(20 * 60)
        ->and(RefreshToken::query()->count())->toBe(0);

    $token = PersonalAccessToken::findToken($fragment['access']);

    expect($token?->tokenable_id)->toBe($this->customer->id)
        ->and($token?->expires_at?->toDateTimeString())->toBe('2026-09-30 10:20:00');

    $entry = adminEntries()->sole();

    expect($entry->event)->toBe('admin.users.impersonated')
        ->and($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->subject_id)->toBe($this->customer->id)
        ->and($entry->properties['reason'])->toBe('Reproducing a checkout bug reported in ticket 42')
        ->and($entry->properties['token_id'])->toBe($token?->id);
});

it('requires a written reason to impersonate', function (): void {
    actingAs($this->admin)
        ->post("/admin/users/{$this->customer->id}/impersonate", ['reason' => 'short'])
        ->assertSessionHasErrors('reason');

    expect($this->customer->tokens()->count())->toBe(0);
});

it('refuses to impersonate staff without issuing a token', function (): void {
    $moderator = User::factory()->create(['status' => UserStatus::ACTIVE])->assignRole('moderator');

    actingAs($this->admin)
        ->post("/admin/users/{$moderator->id}/impersonate", ['reason' => 'Checking what the moderator sees'])
        ->assertSessionHas('error');

    expect($moderator->tokens()->count())->toBe(0)
        ->and(adminEntries()->count())->toBe(0);
});
