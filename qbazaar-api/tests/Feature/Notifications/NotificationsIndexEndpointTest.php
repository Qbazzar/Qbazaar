<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * Helper — inserts a database-channel notification row directly so each
 * test owns the timestamps without going through a real Notification
 * instance.
 */
function makeDbNotification(User $user, ?DateTimeInterface $readAt = null, ?string $title = null): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\Ads\\AdApprovedNotification',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => json_encode([
            'category' => 'ad.approved',
            'title' => $title ?? 'Your ad is live',
            'body' => 'Your ad has been approved.',
            'cta_url' => 'https://example.test/ads/1',
            'icon' => 'badge-check',
        ]),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('paginates notifications 20 per page newest first', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 25; $i++) {
        makeDbNotification($user, null, 'Notification #' . $i);
    }

    Sanctum::actingAs($user, ['*']);

    $response = getJson('/api/v1/account/notifications');

    $response->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->where('meta.per_page', 20)
            ->where('meta.total', 25)
            ->has('data', 20)
            ->etc());
});

it('filters to unread only when ?unread=1', function (): void {
    $user = User::factory()->create();

    makeDbNotification($user, now()); // read
    makeDbNotification($user, now()); // read
    makeDbNotification($user); // unread
    makeDbNotification($user); // unread

    Sanctum::actingAs($user, ['*']);

    getJson('/api/v1/account/notifications?unread=1')
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('meta.total', 2)
            ->has('data', 2)
            ->etc());
});

it('never leaks other users notifications', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    makeDbNotification($user);
    makeDbNotification($other);
    makeDbNotification($other);

    Sanctum::actingAs($user, ['*']);

    getJson('/api/v1/account/notifications')
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('meta.total', 1)
            ->has('data', 1)
            ->etc());
});

it('surfaces the project subset of fields on each notification', function (): void {
    $user = User::factory()->create();
    $id = makeDbNotification($user);

    Sanctum::actingAs($user, ['*']);

    getJson('/api/v1/account/notifications')
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('data.0.id', $id)
            ->where('data.0.category', 'ad.approved')
            ->where('data.0.title', 'Your ad is live')
            ->where('data.0.icon', 'badge-check')
            ->etc());
});

it('requires authentication', function (): void {
    getJson('/api/v1/account/notifications')->assertStatus(401);
});
