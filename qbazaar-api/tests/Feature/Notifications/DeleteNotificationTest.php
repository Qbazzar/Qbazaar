<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;

uses(RefreshDatabase::class);

function seedNotification(User $user): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\Ads\\AdApprovedNotification',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => json_encode(['title' => 't', 'body' => 'b']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('deletes a single notification owned by the caller', function (): void {
    $user = User::factory()->create();
    $id = seedNotification($user);

    Sanctum::actingAs($user, ['*']);

    deleteJson('/api/v1/account/notifications/' . $id)
        ->assertStatus(204);

    expect(DB::table('notifications')->where('id', $id)->exists())->toBeFalse();
});

it('refuses to delete another users notification', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $id = seedNotification($other);

    Sanctum::actingAs($user, ['*']);

    deleteJson('/api/v1/account/notifications/' . $id)
        ->assertStatus(403)
        ->assertJson(fn ($json) => $json->where('error.code', 'NOTIF_003')->etc());

    expect(DB::table('notifications')->where('id', $id)->exists())->toBeTrue();
});

it('returns 404 when notification id is unknown', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    deleteJson('/api/v1/account/notifications/' . Str::uuid())
        ->assertStatus(404)
        ->assertJson(fn ($json) => $json->where('error.code', 'NOTIF_001')->etc());
});
