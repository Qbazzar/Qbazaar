<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\AdStatus;
use App\Enums\OrderStatus;
use App\Enums\SettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\Message;
use App\Models\Order;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardAccess;
use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Services\Admin\Dashboard\DashboardStatsService;
use App\Services\Admin\Dashboard\SchedulerHeartbeat;
use App\Services\Admin\Dashboard\StorageProbe;
use App\Support\Money;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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

it('serves the second request from the cache with no queries at all', function (): void {
    $admin = homeStaff('super_admin');
    $service = app(DashboardStatsService::class);
    $service->forUser($admin, DashboardPeriod::WEEK);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $service->forUser($admin, DashboardPeriod::WEEK);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
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
            // Members with photos exercise the avatar eager loads, messages the daily series.
            $member = User::factory()->create();
            $member->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('avatar');
            Message::factory()->create(['sender_id' => $member->id]);
            SupportTicket::query()->create(['user_id' => $member->id, 'subject' => 'S', 'category' => 'general', 'body' => 'b']);
        }
    };

    $seed(1);
    $queries(); // warm the permission cache so only dashboard queries are counted
    $few = $queries();
    $seed(12);

    // 47, plus the follow tile, the follow and new-chat series and the most-followed list (AD-17.6).
    expect($queries())->toBe($few)->and($few)->toBeLessThanOrEqual(51);
});

it('caches per period and per permission set', function (): void {
    $service = app(DashboardStatsService::class);
    $admin = homeStaff('super_admin');
    $moderator = homeStaff('moderator');

    $service->forUser($admin, DashboardPeriod::WEEK);
    $service->forUser($admin, DashboardPeriod::MONTH);
    $service->forUser($moderator, DashboardPeriod::WEEK);

    $keys = collect(['7d', '30d'])->map(fn (string $p): string => 'admin:dashboard:v3:' . $p . ':' . md5(implode(',', DashboardAccess::sectionsFor($admin))));
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

it('keeps the language of the admin who built the cache out of what the others read', function (): void {
    Report::factory()->create();
    SupportTicket::query()->create(['subject' => 'Help', 'category' => 'general', 'body' => 'x', 'status' => 'open', 'priority' => 'normal', 'email' => 'a@example.test']);
    $admin = homeStaff('super_admin');

    app()->setLocale('ar');
    app(DashboardStatsService::class)->forUser($admin, DashboardPeriod::WEEK);

    app()->setLocale('en');
    actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Spam')
        ->assertSee('Open')
        ->assertDontSee('إعلان مزعج');

    app()->setLocale('ar');
    $this->get(route('admin.dashboard'))->assertSee('إعلان مزعج')->assertSee('مفتوحة');
});

it('does not hand a moderator the finance payload another role cached', function (): void {
    Order::factory()->create(['ad_id' => $this->makeAd(User::factory()->create())->id, 'status' => OrderStatus::COMPLETED->value, 'completed_at' => now(), 'total' => '777.00']);
    CommissionSettlement::query()->forceCreate(['user_id' => User::factory()->create()->id, 'method' => 'bank_transfer', 'status' => SettlementStatus::PENDING->value, 'amount' => '91.00']);

    actingAs(homeStaff('super_admin'))->get(route('admin.dashboard', ['period' => '7d']))->assertOk()->assertSee('777.00');

    actingAs(homeStaff('moderator'))->get(route('admin.dashboard', ['period' => '7d']))
        ->assertOk()
        ->assertDontSee('777.00')
        ->assertDontSee('91.00')
        ->assertDontSee(__('admin.home.tiles.orders_gmv'))
        ->assertDontSee(__('admin.home.system.title'))
        ->assertDontSee(route('admin.finance.settlements.index', [], false), false)
        ->assertDontSee('/admin/finance/', false);
});

it('splits finance.view from finance.manage', function (): void {
    Order::factory()->create(['ad_id' => $this->makeAd(User::factory()->create())->id, 'status' => OrderStatus::COMPLETED->value, 'completed_at' => now(), 'total' => '777.00']);
    $viewer = homeStaff('support');
    $viewer->givePermissionTo('finance.view');

    $viewerStats = app(DashboardStatsService::class)->forUser($viewer, DashboardPeriod::WEEK);
    expect(tile($viewerStats, 'orders_gmv')['value'])->toBe('777.00')
        ->and(collect($viewerStats['attention'])->pluck('key')->all())->not->toContain('pending_settlements', 'pending_withdrawals', 'disputes');

    $manager = homeStaff('support');
    $manager->givePermissionTo('finance.view', 'finance.manage');
    $managerStats = app(DashboardStatsService::class)->forUser($manager, DashboardPeriod::WEEK);
    expect(collect($managerStats['attention'])->pluck('key')->all())->toContain('pending_settlements', 'pending_withdrawals', 'disputes')
        ->and($managerStats)->not->toHaveKey('system');
});

it('gives no trend when the previous period is empty', function (): void {
    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK);

    expect(tile($stats, 'users_new')['previous'])->toBe(0)
        ->and(tile($stats, 'users_new')['change'])->toBeNull()
        ->and(tile($stats, 'orders_gmv')['change'])->toBeNull();
});

it('cuts the windows and the daily series at Qatar midnight', function (): void {
    // 21:30 UTC is 00:30 the next day in Qatar.
    Carbon::setTestNow('2026-10-12 21:30:00');
    User::query()->where('email', 'admin@qbazaar.qa')->update(['created_at' => '2026-10-01 10:00:00']);
    User::factory()->create(['created_at' => '2026-10-12 20:59:00']);
    User::factory()->create(['created_at' => '2026-10-12 21:01:00']);

    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::TODAY);
    $days = collect($stats['series']['users'])->pluck('count', 'date');

    // Today (13 Oct, Qatar) holds the 21:01 signup and the staff member; yesterday holds the 20:59 one.
    expect(tile($stats, 'users_new')['value'])->toBe(2)
        ->and($days['2026-10-13'])->toBe(2)
        ->and($days['2026-10-12'])->toBe(1)
        ->and(array_key_last($days->all()))->toBe('2026-10-13');

    Carbon::setTestNow();
});

it('counts completed orders by completion date, like GMV', function (): void {
    $ad = $this->makeAd(User::factory()->create());
    Order::factory()->create(['ad_id' => $ad->id, 'status' => OrderStatus::COMPLETED->value, 'created_at' => now()->subDays(40), 'completed_at' => now()->subHour()]);

    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK);

    expect(tile($stats, 'orders_completed')['value'])->toBe(1)->and(tile($stats, 'orders_total')['value'])->toBe(0);
});

it('shows failed jobs from the same probe in the health card and the attention list', function (): void {
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

    $stats = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK);
    $attention = collect($stats['attention'])->keyBy('key');

    expect($stats['system']['failed_jobs'])->toBe(1)->and($attention['failed_jobs']['count'])->toBe(1);
});

it('reports every unreachable dependency instead of hanging or failing', function (): void {
    config([
        'scout.driver' => 'meilisearch',
        'scout.meilisearch.host' => 'http://meili.test',
        'queue.default' => 'redis',
        'database.redis.health.host' => '127.0.0.1',
        'database.redis.health.port' => 1,
    ]);
    Http::fake(fn () => throw new ConnectionException('timed out'));
    app()->instance(StorageProbe::class, new class extends StorageProbe
    {
        public function check(string $disk): void
        {
            throw new RuntimeException('bucket unreachable');
        }
    });
    Cache::put(SchedulerHeartbeat::CACHE_KEY, now()->subMinutes(10)->getTimestamp());

    $system = app(DashboardStatsService::class)->forUser(homeStaff('super_admin'), DashboardPeriod::WEEK)['system'];

    expect($system['search']['status'])->toBe('unavailable')
        ->and($system['queues']['status'])->toBe('unavailable')
        ->and($system['storage']['status'])->toBe('unavailable')
        ->and($system['scheduler']['status'])->toBe('stale');
});

it('leaves the shared redis and disk configuration without probe timeouts', function (): void {
    expect(config('database.redis.default.timeout'))->toBeNull()
        ->and(config('database.redis.default.read_timeout'))->toBeNull()
        ->and(config('filesystems.disks.r2.http'))->toBeNull()
        ->and(config('queue.connections.redis.connection'))->toBe('default')
        ->and(config('database.redis.health.read_timeout'))->toBeLessThanOrEqual(2);
});

it('adds and drops the dashboard indexes', function (): void {
    $migration = require database_path('migrations/2026_10_12_100000_add_admin_dashboard_indexes.php');
    $present = fn (): bool => Schema::hasIndex('messages', 'messages_created_at_idx')
        && Schema::hasIndex('ad_promotions', 'ad_promotions_status_activated_idx')
        && Schema::hasIndex('orders', 'orders_created_status_idx');

    expect($present())->toBeTrue();

    $migration->down();
    expect($present())->toBeFalse();

    $migration->up();
    expect($present())->toBeTrue();
});
