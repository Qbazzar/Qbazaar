<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function insertNotif(User $user, ?DateTimeInterface $readAt = null): void
{
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Ads\\AdApprovedNotification',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => json_encode(['title' => 't', 'body' => 'b']),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('returns the unread count for the caller only', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    insertNotif($user);
    insertNotif($user);
    insertNotif($user, now()); // read
    insertNotif($other); // not the caller

    Sanctum::actingAs($user, ['*']);

    getJson('/api/v1/account/notifications/unread-count')
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('data.total', 2)->etc());
});

it('returns zero when the caller has no notifications', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    getJson('/api/v1/account/notifications/unread-count')
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('data.total', 0)->etc());
});
