<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function seedUnreadFor(User $user, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Ads\\AdApprovedNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => json_encode(['category' => 'ad.approved', 'title' => 't', 'body' => 'b']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

it('marks every unread notification as read for the caller', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    seedUnreadFor($user, 5);
    seedUnreadFor($other, 3);

    Sanctum::actingAs($user, ['*']);

    postJson('/api/v1/account/notifications/read-all')
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('data.marked', 5)->etc());

    expect(DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->whereNull('read_at')
        ->count())->toBe(0);

    // Other user's notifications are untouched.
    expect(DB::table('notifications')
        ->where('notifiable_id', $other->id)
        ->whereNull('read_at')
        ->count())->toBe(3);
});

it('returns marked=0 when the caller has no unread notifications', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    postJson('/api/v1/account/notifications/read-all')
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('data.marked', 0)->etc());
});
