<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\Message;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Services\Admin\Dashboard\DashboardStatsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    Cache::flush();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();
});

function engagementStaff(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

function engagementFollow(User $follower, User $followed): void
{
    Follow::query()->create(['follower_id' => $follower->id, 'followed_id' => $followed->id]);
    $followed->increment('followers_count');
}

it('counts follows and charts messages, new chats and follows over 30 days', function (): void {
    $seller = User::factory()->create(['account_type' => AccountType::BUSINESS, 'full_name' => 'Doha Electronics']);
    engagementFollow(User::factory()->create(), $seller);
    engagementFollow(User::factory()->create(), $seller);
    $conversation = Conversation::factory()->create();
    Message::factory()->count(3)->create(['conversation_id' => $conversation->id, 'sender_id' => $conversation->buyer_id]);

    $stats = app(DashboardStatsService::class)->forUser(engagementStaff('super_admin'), DashboardPeriod::WEEK);
    $tiles = collect($stats['groups'])->flatMap(fn (array $group): array => $group['tiles'])->keyBy('key');

    expect($tiles['follows_new']['value'])->toBe(2)
        ->and($tiles['messages_new']['value'])->toBe(3)
        ->and($tiles['conversations_new']['value'])->toBe(1)
        ->and(array_sum(array_column($stats['series']['follows'], 'count')))->toBe(2)
        ->and(array_sum(array_column($stats['series']['conversations'], 'count')))->toBe(1)
        ->and($stats['series']['follows'])->toHaveCount(30)
        ->and($stats['recent']['top_followed'][0])->toMatchArray(['title' => 'Doha Electronics', 'meta' => '2']);
});

it('shows the engagement card and the most followed businesses to super_admin', function (): void {
    $seller = User::factory()->create(['account_type' => AccountType::BUSINESS, 'full_name' => 'Pearl Motors']);
    engagementFollow(User::factory()->create(), $seller);
    actingAs(engagementStaff('super_admin'));

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.home.engagement.title'))
        ->assertSee('data-engagement="messages"', false)
        ->assertSee('data-engagement="conversations"', false)
        ->assertSee('data-engagement="follows"', false)
        ->assertSee(__('admin.home.recent_tabs.top_followed'))
        ->assertSee('Pearl Motors');
});

it('gates each engagement chart by the permission behind it', function (string $role, array $visible, array $hidden): void {
    actingAs(engagementStaff($role));

    $response = $this->get(route('admin.dashboard'))->assertOk();

    foreach ($visible as $chart) {
        $response->assertSee('data-engagement="' . $chart . '"', false);
    }
    foreach ($hidden as $chart) {
        $response->assertDontSee('data-engagement="' . $chart . '"', false);
    }
})->with([
    // moderator: users.view + conversations.view; support: users.view only.
    'moderator' => ['moderator', ['messages', 'conversations', 'follows'], []],
    'support' => ['support', ['follows'], ['messages', 'conversations']],
]);

it('hides the card from staff who may see neither users nor conversations', function (): void {
    Role::findByName('moderator', 'web')->revokePermissionTo(['users.view', 'conversations.view']);
    actingAs(engagementStaff('moderator'));

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(__('admin.home.engagement.title'))
        ->assertDontSee(__('admin.home.recent_tabs.top_followed'));
});

it('lists only active business accounts with followers, most followed first', function (): void {
    $top = User::factory()->create(['account_type' => AccountType::BUSINESS, 'full_name' => 'Top Shop']);
    $second = User::factory()->create(['account_type' => AccountType::BUSINESS, 'full_name' => 'Second Shop']);
    $private = User::factory()->create(['account_type' => AccountType::PRIVATE_INDIVIDUAL, 'full_name' => 'Private Seller']);
    $suspended = User::factory()->create(['account_type' => AccountType::BUSINESS, 'full_name' => 'Suspended Shop', 'status' => 'suspended']);
    foreach ([$top, $top, $top, $second, $private, $private, $private, $private, $suspended, $suspended, $suspended, $suspended] as $followed) {
        engagementFollow(User::factory()->create(), $followed);
    }

    $stats = app(DashboardStatsService::class)->forUser(engagementStaff('super_admin'), DashboardPeriod::WEEK);

    expect(array_column($stats['recent']['top_followed'], 'title'))->toBe(['Top Shop', 'Second Shop']);
});
