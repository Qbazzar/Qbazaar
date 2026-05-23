<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Jobs\DeleteAccountJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'password' => Hash::make('Str0ng!Pass1'),
    ]);
    Sanctum::actingAs($this->user, ['*']);
    Bus::fake();
});

it('schedules deletion, flips status to PENDING_DELETION, and queues DeleteAccountJob', function (): void {
    deleteJson('/api/v1/account/delete-request', [
        'password' => 'Str0ng!Pass1',
        'reason' => 'Done with classifieds',
    ])
        ->assertStatus(202)
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.status', 'pending_deletion')
                ->has('data.deletion_scheduled_at')
                ->etc(),
        );

    $fresh = $this->user->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe(UserStatus::PENDING_DELETION);
    expect($fresh->deletion_requested_at)->not->toBeNull();

    Bus::assertDispatched(DeleteAccountJob::class, function (DeleteAccountJob $job): bool {
        return $job->userId === $this->user->id;
    });
});

it('rejects when the password is wrong with USER_005', function (): void {
    deleteJson('/api/v1/account/delete-request', [
        'password' => 'WrongPass!1',
    ])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'USER_005')
                ->etc(),
        );

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->user->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('rejects unauthenticated requests', function (): void {
    $this->refreshApplication();

    deleteJson('/api/v1/account/delete-request', [
        'password' => 'Str0ng!Pass1',
    ])->assertStatus(401);
});
