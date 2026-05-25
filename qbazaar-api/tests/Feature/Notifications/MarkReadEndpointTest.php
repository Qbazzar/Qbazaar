<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function insertNotification(User $user, ?DateTimeInterface $readAt = null): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\Ads\\AdApprovedNotification',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => json_encode(['category' => 'ad.approved', 'title' => 't', 'body' => 'b']),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('marks a single notification as read', function (): void {
    $user = User::factory()->create();
    $id = insertNotification($user);

    Sanctum::actingAs($user, ['*']);

    postJson('/api/v1/account/notifications/' . $id . '/read')
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('data.id', $id)
            ->whereNot('data.read_at', null)
            ->etc());

    expect(DB::table('notifications')->where('id', $id)->value('read_at'))
        ->not->toBeNull();
});

it('returns NOTIF_FORBIDDEN when notification belongs to another user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $id = insertNotification($other);

    Sanctum::actingAs($user, ['*']);

    postJson('/api/v1/account/notifications/' . $id . '/read')
        ->assertStatus(403)
        ->assertJson(fn ($json) => $json->where('error.code', 'NOTIF_003')->etc());
});

it('returns NOTIF_NOT_FOUND when notification does not exist', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    postJson('/api/v1/account/notifications/' . Str::uuid() . '/read')
        ->assertStatus(404)
        ->assertJson(fn ($json) => $json->where('error.code', 'NOTIF_001')->etc());
});

it('is idempotent on already-read notifications', function (): void {
    $user = User::factory()->create();
    $id = insertNotification($user, now()->subDay());

    Sanctum::actingAs($user, ['*']);

    postJson('/api/v1/account/notifications/' . $id . '/read')
        ->assertOk();
});
