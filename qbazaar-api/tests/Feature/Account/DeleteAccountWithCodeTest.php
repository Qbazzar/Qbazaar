<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Jobs\DeleteAccountJob;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\putJson;

use Tests\Concerns\RequestsReauthCodes;

uses(RefreshDatabase::class, RequestsReauthCodes::class);

beforeEach(function (): void {
    Notification::fake();
    Bus::fake();

    $this->passwordless = User::factory()->create(['password' => null]);
    $this->withPassword = User::factory()->create(['password' => Hash::make('Str0ng!Pass1')]);
});

it('schedules the deletion of an account without a password with an emailed code', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);

    deleteJson('/api/v1/account/delete-request', [
        'reauth_code' => $this->requestReauthCode($this->passwordless),
        'reason' => 'Done with classifieds',
    ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending_deletion')
        ->assertJsonStructure(['data' => ['deletion_scheduled_at', 'status']]);

    expect($this->passwordless->fresh()->status)->toBe(UserStatus::PENDING_DELETION);
    Bus::assertDispatched(DeleteAccountJob::class, fn (DeleteAccountJob $job): bool => $job->userId === $this->passwordless->id);
});

it('accepts a code from an account that has a password', function (): void {
    Sanctum::actingAs($this->withPassword, ['*']);

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => $this->requestReauthCode($this->withPassword)])
        ->assertStatus(202);

    expect($this->withPassword->fresh()->status)->toBe(UserStatus::PENDING_DELETION);
});

it('rejects a wrong code with ACCOUNT_001', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);
    $code = $this->requestReauthCode($this->passwordless);

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => $this->wrongReauthCode($code)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->passwordless->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('rejects an expired code', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);
    $code = $this->requestReauthCode($this->passwordless);

    $this->travel(11)->minutes();

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    expect($this->passwordless->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('does not accept a code already spent on a password change', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);
    $code = $this->requestReauthCode($this->passwordless);

    putJson('/api/v1/account/password', ['reauth_code' => $code, 'password' => 'NewStr0ng!Pass#1', 'password_confirmation' => 'NewStr0ng!Pass#1'])
        ->assertNoContent();

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    expect($this->passwordless->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('checks the code before revealing any debt', function (): void {
    $order = Order::factory()->status(OrderStatus::COMPLETED)->create([
        'ad_id' => null,
        'seller_id' => $this->passwordless->id,
        'commission_amount' => '12.50',
    ]);
    app(LedgerRecipes::class)->chargeCommission($order, LedgerActor::system());
    Sanctum::actingAs($this->passwordless, ['*']);
    $code = $this->requestReauthCode($this->passwordless);

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => $this->wrongReauthCode($code)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_003')
        ->assertJsonPath('error.details.amount', '12.50');

    expect($this->passwordless->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('refuses the password and a code together without spending the code', function (): void {
    Sanctum::actingAs($this->withPassword, ['*']);
    $code = $this->requestReauthCode($this->withPassword);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1', 'reauth_code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.password.0', __('users.step_up.only_one'));

    expect($this->withPassword->fresh()->status)->toBe(UserStatus::ACTIVE);

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => $code])->assertStatus(202);
});

it('refuses a deletion confirmed with neither the password nor a code', function (): void {
    Sanctum::actingAs($this->withPassword, ['*']);

    deleteJson('/api/v1/account/delete-request', ['reason' => 'Leaving'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password', 'reauth_code'], 'error.details');

    Bus::assertNotDispatched(DeleteAccountJob::class);
});

it('tells an account without a password to confirm with the code', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Guess!Pass1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.password.0', __('users.step_up.no_password'));

    deleteJson('/api/v1/account/delete-request', [])
        ->assertStatus(422)
        ->assertJsonPath('error.details.reauth_code.0', __('users.step_up.code_required'));

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->passwordless->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('caps code attempts per hour like the email change', function (): void {
    config()->set('qbazaar.account.contact_change_attempts_per_hour', 2);
    Sanctum::actingAs($this->passwordless, ['*']);

    deleteJson('/api/v1/account/delete-request', ['reauth_code' => '000000'])->assertStatus(422);
    deleteJson('/api/v1/account/delete-request', ['reauth_code' => '000000'])->assertStatus(422);
    deleteJson('/api/v1/account/delete-request', ['reauth_code' => '000000'])->assertStatus(429);

    Bus::assertNotDispatched(DeleteAccountJob::class);
});
