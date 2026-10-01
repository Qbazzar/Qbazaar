<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

function addressPayload(array $overrides = []): array
{
    return [
        'label' => 'Home',
        'full_name' => 'Ahmed Al-Ali',
        'phone' => '+97455123456',
        'street' => 'Al Sadd Street',
        'house_number' => '12',
        'supplement' => 'Flat 4',
        'city' => 'Doha',
        'postal_code' => null,
        ...$overrides,
    ];
}

function defaultAddressIds(User $user): array
{
    return UserAddress::query()->ownedBy($user)->where('is_default', true)->pluck('id')->all();
}

it('creates the first address as the default', function (): void {
    postJson('/api/v1/account/addresses', addressPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.full_name', 'Ahmed Al-Ali')
        ->assertJsonPath('data.is_default', true);
});

it('moves the default when a new address asks for it', function (): void {
    $first = UserAddress::factory()->for($this->user)->default()->create();

    $id = postJson('/api/v1/account/addresses', addressPayload(['is_default' => true]))
        ->assertCreated()
        ->json('data.id');

    expect(defaultAddressIds($this->user))->toBe([$id])
        ->and($first->fresh()->is_default)->toBeFalse();
});

it('keeps the current default when a new address does not ask for it', function (): void {
    $first = UserAddress::factory()->for($this->user)->default()->create();

    postJson('/api/v1/account/addresses', addressPayload())
        ->assertCreated()
        ->assertJsonPath('data.is_default', false);

    expect(defaultAddressIds($this->user))->toBe([$first->id]);
});

it('lists only the caller addresses with the default first', function (): void {
    UserAddress::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
    $default = UserAddress::factory()->for($this->user)->default()->create(['created_at' => now()->subDays(2)]);
    UserAddress::factory()->create();

    getJson('/api/v1/account/addresses')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $default->id);
});

it('refuses an eleventh address', function (): void {
    UserAddress::factory()->for($this->user)->count(10)->create();

    postJson('/api/v1/account/addresses', addressPayload())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ADDRESS_002');

    expect(UserAddress::query()->ownedBy($this->user)->count())->toBe(10);
});

it('patches a single field', function (): void {
    $address = UserAddress::factory()->for($this->user)->default()->create(['city' => 'Doha']);

    patchJson("/api/v1/account/addresses/{$address->id}", ['city' => 'Al Wakrah'])
        ->assertOk()
        ->assertJsonPath('data.city', 'Al Wakrah')
        ->assertJsonPath('data.street', $address->street);
});

it('makes a patched address the only default', function (): void {
    $old = UserAddress::factory()->for($this->user)->default()->create();
    $other = UserAddress::factory()->for($this->user)->create();

    patchJson("/api/v1/account/addresses/{$other->id}", ['is_default' => true])
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect(defaultAddressIds($this->user))->toBe([$other->id])
        ->and($old->fresh()->is_default)->toBeFalse();
});

it('does not let the only default be switched off', function (): void {
    $address = UserAddress::factory()->for($this->user)->default()->create();

    patchJson("/api/v1/account/addresses/{$address->id}", ['is_default' => false])
        ->assertOk()
        ->assertJsonPath('data.is_default', true);
});

it('promotes the newest remaining address when the default is deleted', function (): void {
    $default = UserAddress::factory()->for($this->user)->default()->create(['created_at' => now()->subDays(3)]);
    UserAddress::factory()->for($this->user)->create(['created_at' => now()->subDays(2)]);
    $newest = UserAddress::factory()->for($this->user)->create(['created_at' => now()->subDay()]);

    deleteJson("/api/v1/account/addresses/{$default->id}")->assertNoContent();

    expect(UserAddress::query()->find($default->id))->toBeNull()
        ->and(defaultAddressIds($this->user))->toBe([$newest->id]);
});

it('hides other users addresses behind ADDRESS_001', function (): void {
    $foreign = UserAddress::factory()->create();

    patchJson("/api/v1/account/addresses/{$foreign->id}", ['city' => 'X'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'ADDRESS_001');

    deleteJson("/api/v1/account/addresses/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'ADDRESS_001');

    expect($foreign->fresh())->not->toBeNull();
});

it('validates the address payload', function (): void {
    postJson('/api/v1/account/addresses', ['phone' => '12345', 'location_id' => 'missing'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors(['full_name', 'street', 'house_number', 'city', 'phone', 'location_id'], 'error.details');
});

it('rejects markup in address text', function (): void {
    postJson('/api/v1/account/addresses', addressPayload(['street' => '<script>x</script>']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['street'], 'error.details');
});

it('requires authentication', function (): void {
    $this->refreshApplication();

    getJson('/api/v1/account/addresses')->assertStatus(401);
    postJson('/api/v1/account/addresses', addressPayload())->assertStatus(401);
});
