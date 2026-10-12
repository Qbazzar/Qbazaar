<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\AdStatus;
use App\Enums\OrderStatus;
use App\Enums\SettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\Order;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardAccess;
use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Services\Admin\Dashboard\DashboardStatsService;
use App\Services\Admin\Dashboard\SchedulerHeartbeat;
use App\Support\Money;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    Cache::flush();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();
});

function homeStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function tile(array $stats, string $key): array
{
    foreach ($stats['groups'] as $group) {
        foreach ($group['tiles'] as $tile) {
            if ($tile['key'] === $key) {
                return $tile;
            }
        }
    }

    throw new RuntimeException("No tile [{$key}]");
}

it('renames the sidebar item to Home with the home icon, in Arabic and English', function (): void {
    $homeIcon = 'm2.25 12 8.954-8.955';

    app()->setLocale('ar');
    actingAs(homeStaff('super_admin'))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('الرئيسية')
        ->assertDontSee('لوحة القيادة')
        ->assertSee($homeIcon, false);

    app()->setLocale('en');
    actingAs(homeStaff('super_admin'))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Home')
        ->assertSee('<title>Home', false);
});

it('shows each role only its own sections', function (string $role, array $sees, array $hides): void {
    actingAs(homeStaff($role))->get(route('admin.dashboard'))->assertOk();
    $response = $this->get(route('admin.dashboard'));

    foreach ($sees as $key) {
        $response->assertSee(__($key), false);
    }
    foreach ($hides as $key) {
        $response->assertDontSee(__($key), false);
    }
})->with([
    'super admin' => ['super_admin', [
        'admin.home.tiles.orders_gmv', 'admin.home.tiles.commission_earned', 'admin.home.system.title',
        'admin.home.attention.items.pending_withdrawals', 'admin.home.attention.items.failed_jobs', 'admin.home.tiles.users_new',
    ], []],
    'moderator' => ['moderator', [
        'admin.home.tiles.users_new', 'admin.home.tiles.ads_pending', 'admin.home.attention.items.pending_ads',
        'admin.home.attention.items.open_reports', 'admin.home.tiles.messages_new',
    ], [
        'admin.home.tiles.orders_gmv', 'admin.home.tiles.commission_earned', 'admin.home.tiles.wallet_balances',
        'admin.home.attention.items.pending_settlements', 'admin.home.attention.items.pending_withdrawals',
        'admin.home.system.title',
    ]],
    'support' => ['support', [
        'admin.home.attention.items.open_tickets', 'admin.home.tiles.tickets_new', 'admin.home.recent.tickets',
    ], [
        'admin.home.tiles.orders_gmv', 'admin.home.tiles.commission_earned', 'admin.home.system.title',
        'admin.home.attention.items.pending_ads', 'admin.home.tiles.messages_new',
    ]],
]);

it('keeps finance links and money figures away from moderators and support', function (string $role): void {
    Order::factory()->create(['ad_id' => $this->makeAd(User::factory()->create())->id, 'status' => OrderStatus::COMPLETED->value, 'completed_at' => now(), 'total' => '777.00']);

    actingAs(homeStaff($role))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('777.00')
        ->assertDontSee(route('admin.finance.withdrawals.index', [], false), false)
        ->assertDontSee(route('admin.finance.settlements.index', [], false), false);
})->with(['moderator', 'support']);

it('reports the seeded fixtures exactly', function (): void {
    Carbon::setTestNow('2026-10-12 12:00:00');
    $seller = User::factory()->create(['account_type' => AccountType::BUSINESS->value, 'phone_verified' => true, 'last_login_at' => now()->subDay()]);
    User::factory()->count(2)->create(['account_type' => AccountType::PRIVATE_INDIVIDUAL->value, 'phone_verified' => false, 'created_at' => now()->subDays(20)]);
    $ad = $this->makeAd($seller, ['status' => AdStatus::ACTIVE->value]);
    $this->makeAd($seller, ['status' => AdStatus::PENDING->value, 'submitted_at' => now()->subHours(5)]);
    $this->makeAd($seller, ['status' => AdStatus::REJECTED->value]);
    Order::factory()->create(['ad_id' => $ad->id, 'status' => OrderStatus::COMPLETED->value, 'completed_at' => now()->subDay(), 'total' => '100.10', 'commission_amount' => '5.01']);
    Order::factory()->create(['ad_id' => $ad->id, 'status' => OrderStatus::COMPLETED->value, 'completed_at' => now()->subDays(2), 'total' => '250.40', 'commission_amount' => '12.52', 'source_id' => (string) Str::ulid()]);
    Order::factory()->create(['ad_id' => $ad->id, 'status' => OrderStatus::DISPUTED->value, 'disputed_at' => now()->subHour(), 'source_id' => (string) Str::ulid()]);
    CommissionSettlement::query()->forceCreate(['user_id' => $seller->id, 'method' => 'bank_transfer', 'status' => SettlementStatus::PENDING->value, 'amount' => '40.25']);
    Report::factory()->count(2)->create();
    SupportTicket::query()->create(['user_id' => $seller->id, 'subject' => 'Help', 'category' => 'general', 'body' => 'x', 'status' => 'open', 'priority' => 'normal']);

    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK);

    expect(tile($stats, 'ads_active')['value'])->toBe(1)
        ->and(tile($stats, 'ads_pending')['value'])->toBe(1)
        ->and(tile($stats, 'ads_rejected')['value'])->toBe(1)
        ->and(tile($stats, 'users_business')['value'])->toBe(1)
        ->and(tile($stats, 'users_active')['value'])->toBe(1)
        ->and(tile($stats, 'orders_gmv')['value'])->toBe('350.50')
        ->and(tile($stats, 'commission_earned')['value'])->toBe('17.53')
        ->and(tile($stats, 'orders_disputed')['value'])->toBe(1);

    $attention = collect($stats['attention'])->keyBy('key');
    expect($attention['pending_ads']['count'])->toBe(1)
        ->and($attention['pending_ads']['oldest'])->not->toBeNull()
        ->and($attention['open_reports']['count'])->toBe(2)
        ->and($attention['open_tickets']['count'])->toBe(1)
        ->and($attention['pending_settlements']['amount'])->toBe('40.25')
        ->and($attention['disputes']['count'])->toBe(1)
        ->and($stats['series']['users'])->toHaveCount(30)
        ->and($stats['breakdowns']['categories'][0]['count'])->toBe(1);

    actingAs(homeStaff('super_admin'))->get(route('admin.dashboard'))->assertOk()->assertSee('40.25')->assertSee('350.50');

    Carbon::setTestNow();
});

it('compares a period with the one before it', function (): void {
    Carbon::setTestNow('2026-10-12 12:00:00');
    User::factory()->count(3)->create(['created_at' => now()->subDays(2)]);
    User::factory()->count(1)->create(['created_at' => now()->subDays(10)]);

    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK);
    $new = tile($stats, 'users_new');

    // The seeded admin and the staff member created above are signups inside the window too.
    expect($new['value'])->toBe(5)->and($new['previous'])->toBe(1)->and($new['change'])->toBe(400);

    Carbon::setTestNow();
});

it('serves the second request from the cache', function (): void {
    $admin = homeStaff('super_admin');
    actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(DashboardStatsService::class)->forUser($admin, DashboardPeriod::WEEK);
    $statQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'count(') || str_contains($sql, 'COUNT('));
    DB::disableQueryLog();

    expect($statQueries)->toBeEmpty();
});

it('keeps a fixed number of queries however many rows there are', function (): void {
    $admin = homeStaff('super_admin');
    $owner = User::factory()->create();
    $queries = function () use ($admin): int {
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(DashboardStatsService::class)->forUser($admin, DashboardPeriod::WEEK);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $seed = function (int $times) use ($owner): void {
        foreach (range(1, $times) as $_) {
            $ad = $this->makeAd($owner, ['status' => AdStatus::PENDING->value]);
            Report::factory()->create();
            SupportTicket::query()->create(['user_id' => $owner->id, 'subject' => 'S', 'category' => 'general', 'body' => 'b']);
            Order::factory()->create(['ad_id' => $ad->id, 'source_id' => (string) Str::ulid()]);
        }
    };

    $seed(1);
    $queries(); // warm the permission cache so only dashboard queries are counted
    $few = $queries();
    $seed(12);

    expect($queries())->toBe($few)->and($few)->toBeLessThanOrEqual(45);
});

it('caches per period and per permission set', function (): void {
    $service = app(DashboardStatsService::class);
    $admin = homeStaff('super_admin');
    $moderator = homeStaff('moderator');

    $service->forUser($admin, DashboardPeriod::WEEK);
    $service->forUser($admin, DashboardPeriod::MONTH);
    $service->forUser($moderator, DashboardPeriod::WEEK);

    $keys = collect(['7d', '30d'])->map(fn (string $p): string => 'admin:dashboard:v1:' . $p . ':' . md5(implode(',', DashboardAccess::sectionsFor($admin))));
    expect(Cache::has($keys[0]))->toBeTrue()->and(Cache::has($keys[1]))->toBeTrue();
    expect(DashboardAccess::sectionsFor($moderator))->not->toBe(DashboardAccess::sectionsFor($admin));
});

it('falls back to seven days for an unknown period and accepts the others', function (): void {
    actingAs(homeStaff('super_admin'));

    foreach (['today', '30d', 'bogus'] as $period) {
        $this->get(route('admin.dashboard', ['period' => $period]))->assertOk();
    }

    expect(DashboardPeriod::fromRequest('bogus'))->toBe(DashboardPeriod::WEEK);
});

it('probes system health for super admins only', function (): void {
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://meili.test']);
    Http::fake([
        'meili.test/health' => Http::response(['status' => 'available']),
        'meili.test/indexes/*/stats' => Http::response(['numberOfDocuments' => 1234]),
    ]);
    app(SchedulerHeartbeat::class)->beat();

    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK);

    expect($stats['system']['search'])->toBe(['status' => 'ok', 'documents' => 1234])
        ->and($stats['system']['scheduler']['status'])->toBe('ok')
        ->and($stats['system']['storage']['status'])->toBe('ok')
        ->and(app(DashboardStatsService::class)->forUser(homeStaff('moderator'), DashboardPeriod::WEEK))->not->toHaveKey('system');
});

it('reports an unreachable search engine instead of failing', function (): void {
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://meili.test']);
    Http::fake(['meili.test/*' => Http::response('down', 503)]);

    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK);

    expect($stats['system']['search']['status'])->toBe('unavailable')
        ->and($stats['system']['scheduler']['status'])->toBe('unknown');
});

it('formats money without floats', function (): void {
    expect(Money::format('1234567.8'))->toBe('1,234,567.80')
        ->and(Money::format('0'))->toBe('0.00')
        ->and(Money::format('-1500.5'))->toBe('-1,500.50');
});
