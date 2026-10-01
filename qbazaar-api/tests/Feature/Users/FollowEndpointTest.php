<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Jobs\DeleteAccountJob;
use App\Models\Follow;
use App\Models\User;
use App\Services\Users\FollowGraph;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->me = User::factory()->create();
    $this->seller = User::factory()->create();
});

function followCounts(User $user): array
{
    $fresh = $user->fresh();

    return [$fresh?->followers_count, $fresh?->following_count];
}

it('follows a user and updates both counters', function (): void {
    Sanctum::actingAs($this->me, ['*']);

    postJson("/api/v1/users/{$this->seller->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.following', true)
        ->assertJsonPath('data.user_id', $this->seller->id)
        ->assertJsonPath('data.followers_count', 1);

    expect(followCounts($this->seller))->toBe([1, 0])
        ->and(followCounts($this->me))->toBe([0, 1]);
});

it('is idempotent and never double counts', function (): void {
    Sanctum::actingAs($this->me, ['*']);

    postJson("/api/v1/users/{$this->seller->id}/follow")->assertOk();
    postJson("/api/v1/users/{$this->seller->id}/follow")->assertOk()->assertJsonPath('data.followers_count', 1);

    expect(Follow::query()->count())->toBe(1)
        ->and(followCounts($this->seller))->toBe([1, 0]);
});

it('unfollows idempotently and never goes below zero', function (): void {
    Sanctum::actingAs($this->me, ['*']);
    app(FollowGraph::class)->link($this->me, $this->seller);

    deleteJson("/api/v1/users/{$this->seller->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.following', false)
        ->assertJsonPath('data.followers_count', 0);

    deleteJson("/api/v1/users/{$this->seller->id}/follow")->assertOk();

    expect(followCounts($this->seller))->toBe([0, 0])
        ->and(followCounts($this->me))->toBe([0, 0]);
});

it('refuses to follow yourself', function (): void {
    Sanctum::actingAs($this->me, ['*']);

    postJson("/api/v1/users/{$this->me->id}/follow")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'FOLLOW_001');
});

it('refuses to follow across a block in either direction', function (bool $iBlocked): void {
    Sanctum::actingAs($this->me, ['*']);
    [$blocker, $blocked] = $iBlocked ? [$this->me, $this->seller] : [$this->seller, $this->me];
    $blocker->blockedUsers()->attach($blocked->id, ['created_at' => now()]);

    postJson("/api/v1/users/{$this->seller->id}/follow")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FOLLOW_002');

    expect(Follow::query()->count())->toBe(0);
})->with(['I blocked them' => true, 'they blocked me' => false]);

it('removes the follow both ways when one user blocks the other', function (): void {
    $graph = app(FollowGraph::class);
    $graph->link($this->me, $this->seller);
    $graph->link($this->seller, $this->me);
    Sanctum::actingAs($this->me, ['*']);

    postJson("/api/v1/users/{$this->seller->id}/block")->assertOk();

    expect(Follow::query()->count())->toBe(0)
        ->and(followCounts($this->me))->toBe([0, 0])
        ->and(followCounts($this->seller))->toBe([0, 0]);
});

it('returns USER_001 for unknown or inactive users', function (): void {
    Sanctum::actingAs($this->me, ['*']);
    $suspended = User::factory()->suspended()->create();

    postJson("/api/v1/users/{$suspended->id}/follow")->assertNotFound()->assertJsonPath('error.code', 'USER_001');
    postJson('/api/v1/users/01HZZZZZZZZZZZZZZZZZZZZZZZ/follow')->assertNotFound()->assertJsonPath('error.code', 'USER_001');
});

it('requires authentication', function (): void {
    postJson("/api/v1/users/{$this->seller->id}/follow")->assertUnauthorized();
    getJson('/api/v1/account/followers')->assertUnauthorized();
    getJson('/api/v1/account/following')->assertUnauthorized();
});

it('lists followers newest first with a cursor and the follow-back state', function (): void {
    config(['qbazaar.social.follows_per_page' => 2]);
    $graph = app(FollowGraph::class);
    [$first, $second, $third] = User::factory()->count(3)->create()->all();
    $graph->link($first, $this->me);
    $graph->link($second, $this->me);
    $graph->link($third, $this->me);
    $graph->link($this->me, $third);
    $graph->link(User::factory()->suspended()->create(), $this->me);
    Sanctum::actingAs($this->me, ['*']);

    $page = getJson('/api/v1/account/followers')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $third->id)
        ->assertJsonPath('data.0.is_following', true)
        ->assertJsonPath('data.1.id', $second->id)
        ->assertJsonPath('data.1.is_following', false)
        ->assertJsonPath('meta.has_more', true);

    getJson('/api/v1/account/followers?cursor=' . urlencode((string) $page->json('meta.next_cursor')))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('meta.has_more', false);
});

it('lists the accounts I follow', function (): void {
    app(FollowGraph::class)->link($this->me, $this->seller);
    Sanctum::actingAs($this->me, ['*']);

    getJson('/api/v1/account/following')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->seller->id)
        ->assertJsonPath('data.0.is_following', true)
        ->assertJsonPath('data.0.followers_count', 1);
});

it('lists followers without lazy loading or a query per row', function (): void {
    $graph = app(FollowGraph::class);
    User::factory()->count(5)->create()->each(fn (User $user) => $graph->link($user, $this->me));
    Sanctum::actingAs($this->me, ['*']);

    DB::enableQueryLog();
    getJson('/api/v1/account/followers')->assertOk()->assertJsonCount(5, 'data');

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(6);
});

it('removes a follower', function (): void {
    app(FollowGraph::class)->link($this->seller, $this->me);
    Sanctum::actingAs($this->me, ['*']);

    deleteJson("/api/v1/account/followers/{$this->seller->id}")->assertNoContent();
    deleteJson("/api/v1/account/followers/{$this->seller->id}")->assertNoContent();

    expect(Follow::query()->count())->toBe(0)
        ->and(followCounts($this->me))->toBe([0, 0])
        ->and(followCounts($this->seller))->toBe([0, 0]);
});

it('shows the counts and is_following on the public profile', function (): void {
    app(FollowGraph::class)->link($this->me, $this->seller);

    getJson("/api/v1/users/{$this->seller->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.followers_count', 1)
        ->assertJsonPath('data.following_count', 0)
        ->assertJsonPath('data.is_following', false);

    Sanctum::actingAs($this->me, ['*']);

    getJson("/api/v1/users/{$this->seller->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.is_following', true);
});

it('rate limits follow requests', function (): void {
    config(['qbazaar.social.follows_per_minute' => 2]);
    Sanctum::actingAs($this->me, ['*']);

    postJson("/api/v1/users/{$this->seller->id}/follow")->assertOk();
    deleteJson("/api/v1/users/{$this->seller->id}/follow")->assertOk();
    postJson("/api/v1/users/{$this->seller->id}/follow")
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
});

it('lowers the other users counts when an account is deleted for good', function (): void {
    $graph = app(FollowGraph::class);
    $fan = User::factory()->create();
    $graph->link($this->me, $this->seller);
    $graph->link($fan, $this->me);
    $this->me->forceFill(['status' => UserStatus::PENDING_DELETION, 'deletion_requested_at' => User::deletionCutoff()->subMinute()])->save();

    DeleteAccountJob::dispatchSync($this->me->id);

    expect(Follow::query()->count())->toBe(0)
        ->and(followCounts($this->seller))->toBe([0, 0])
        ->and(followCounts($fan))->toBe([0, 0]);
});
