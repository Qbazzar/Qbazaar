<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\Dashboard\AttentionPriority;
use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Services\Admin\Dashboard\DashboardStatsService;
use App\Support\Charts\Palette;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
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

function chartStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function days(int $count, string $field = 'count', int|string $value = 0): array
{
    return array_map(
        static fn (int $i): array => ['date' => Carbon::parse('2026-09-13')->addDays($i)->toDateString(), $field => $value],
        range(0, $count - 1),
    );
}

// --- The new series -----------------------------------------------------------

it('adds messages and completed-order money to the daily series, zero-filled', function (): void {
    Carbon::setTestNow('2026-10-12 12:00:00');
    $ad = $this->makeAd(User::factory()->create());
    Order::factory()->create(['ad_id' => $ad->id, 'status' => OrderStatus::COMPLETED->value, 'completed_at' => now()->subDay(), 'total' => '100.10', 'commission_amount' => '5.01']);
    Order::factory()->create(['ad_id' => $ad->id, 'status' => OrderStatus::COMPLETED->value, 'completed_at' => now()->subDay(), 'total' => '50.00', 'commission_amount' => '2.50', 'source_id' => (string) Str::ulid()]);
    Order::factory()->create(['ad_id' => $ad->id, 'status' => OrderStatus::CANCELLED->value, 'completed_at' => now()->subDay(), 'total' => '999.00', 'source_id' => (string) Str::ulid()]);

    $stats = app(DashboardStatsService::class)->forUser(chartStaff('super_admin'), DashboardPeriod::WEEK);
    $revenue = collect($stats['series']['revenue'])->keyBy('date');

    expect($stats['series']['revenue'])->toHaveCount(30)
        ->and($stats['series']['messages'])->toHaveCount(30)
        ->and($revenue['2026-10-11']['gmv'])->toBe('150.10')
        ->and($revenue['2026-10-11']['commission'])->toBe('7.51')
        ->and($revenue['2026-10-12']['gmv'])->toBe('0.00')
        ->and(array_sum(array_column($stats['series']['messages'], 'count')))->toBe(0);

    Carbon::setTestNow();
});

it('gives every viewer only the series their permissions allow', function (string $role, array $has, array $lacks): void {
    $stats = app(DashboardStatsService::class)->forUser(chartStaff($role), DashboardPeriod::WEEK);

    foreach ($has as $key) {
        expect($stats['series'])->toHaveKey($key);
    }
    foreach ($lacks as $key) {
        expect($stats['series'])->not->toHaveKey($key)->and($stats['distributions'])->not->toHaveKey($key);
    }
})->with([
    'super admin' => ['super_admin', ['users', 'ads', 'orders', 'messages', 'revenue'], []],
    'moderator' => ['moderator', ['users', 'ads', 'messages'], ['orders', 'revenue']],
    'support' => ['support', [], ['revenue', 'orders']],
]);

it('splits ads and orders by status, finance only for orders', function (): void {
    $owner = User::factory()->create();
    $this->makeAd($owner, ['status' => AdStatus::ACTIVE->value]);
    $this->makeAd($owner, ['status' => AdStatus::ACTIVE->value]);
    $this->makeAd($owner, ['status' => AdStatus::PENDING->value]);
    Order::factory()->create(['ad_id' => $this->makeAd($owner)->id, 'status' => OrderStatus::DISPUTED->value, 'disputed_at' => now(), 'source_id' => (string) Str::ulid()]);

    $admin = app(DashboardStatsService::class)->forUser(chartStaff('super_admin'), DashboardPeriod::WEEK)['distributions'];
    $moderator = app(DashboardStatsService::class)->forUser(chartStaff('moderator'), DashboardPeriod::WEEK)['distributions'];

    expect($admin['ads'][AdStatus::ACTIVE->value])->toBe(2)
        ->and($admin['ads'][AdStatus::PENDING->value])->toBe(1)
        ->and($admin['ads'])->toHaveKeys(array_map(fn (AdStatus $s): string => $s->value, AdStatus::cases()))
        ->and($admin['orders'][OrderStatus::DISPUTED->value])->toBe(1)
        ->and($moderator)->toHaveKey('ads')->not->toHaveKey('orders');
});

it('keeps avatars in the recent lists as plain data', function (): void {
    $stats = app(DashboardStatsService::class)->forUser(chartStaff('super_admin'), DashboardPeriod::WEEK);
    $person = $stats['recent']['signups'][0]['person'];

    expect($person)->toHaveKeys(['id', 'name', 'avatar'])->and($person['avatar'])->toBeNull();
});

// --- Needs attention ------------------------------------------------------------

it('orders the queues by urgency and tags a severity', function (): void {
    $now = CarbonImmutable::parse('2026-10-12 12:00:00');
    $item = fn (string $key, int $count, ?string $oldest): array => ['key' => $key, 'count' => $count, 'oldest' => $oldest, 'amount' => null, 'href' => null];

    $sorted = AttentionPriority::sort([
        $item('open_tickets', 0, null),
        $item('open_reports', 4, '2026-10-12 08:00:00'),
        $item('pending_ads', 9, '2026-10-10 08:00:00'),
        $item('failed_jobs', 1, null),
        $item('disputes', 0, null),
    ], $now);

    expect(array_column($sorted, 'key'))->toBe(['pending_ads', 'failed_jobs', 'open_reports', 'open_tickets', 'disputes'])
        ->and(array_column($sorted, 'severity'))->toBe(['critical', 'critical', 'warning', 'clear', 'clear']);
});

it('renders waiting queues as linked cards and empty ones as a clear list', function (): void {
    app()->setLocale('ar');
    $owner = User::factory()->create();
    $this->makeAd($owner, ['status' => AdStatus::PENDING->value, 'submitted_at' => now()->subDays(3)]);

    actingAs(chartStaff('moderator'))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.moderation.queue', [], false), false)
        ->assertSee(__('admin.home.attention.severity.critical'))
        ->assertSee(__('admin.home.attention.clear_items'));
});

it('shows an all-clear state when nothing waits', function (): void {
    actingAs(chartStaff('moderator'))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.home.attention.clear'));
});

// --- Gating on the page -----------------------------------------------------------

it('shows the money charts to finance viewers only', function (): void {
    $moneyChart = __('admin.home.trends.revenue');

    actingAs(chartStaff('super_admin'))->get(route('admin.dashboard'))->assertOk()
        ->assertSee($moneyChart)->assertSee(__('admin.home.distribution.orders'));

    $viewer = chartStaff('support');
    $viewer->givePermissionTo('finance.view');
    actingAs($viewer)->get(route('admin.dashboard'))->assertOk()->assertSee($moneyChart);

    actingAs(chartStaff('moderator'))->get(route('admin.dashboard'))->assertOk()
        ->assertDontSee($moneyChart)
        ->assertDontSee(__('admin.home.distribution.orders'))
        ->assertDontSee(__('admin.home.trends.gmv'))
        ->assertSee(__('admin.home.distribution.ads'));
});

it('puts the recent activity in one tabbed card with avatars', function (): void {
    User::factory()->create(['full_name' => 'Mariam Al Kuwari']);

    actingAs(chartStaff('super_admin'))->get(route('admin.dashboard'))->assertOk()
        ->assertSee('data-tabs="recent"', false)
        ->assertSee('id="recent-panel-signups"', false)
        ->assertSee('Mariam Al Kuwari')
        ->assertSee('role="tablist"', false);
});

it('renders the health row for super admins as status pills', function (): void {
    actingAs(chartStaff('super_admin'))->get(route('admin.dashboard'))->assertOk()
        ->assertSee(__('admin.home.system.title'))
        ->assertSee(__('admin.home.system.failed_jobs'))
        ->assertSee(__('admin.home.system.scheduler'));
});

// --- Chart components ----------------------------------------------------------------

it('renders the sparkline with a summary, a table and a flat baseline when empty', function (): void {
    $flat = Blade::render('<x-admin.chart.sparkline :points="$points" label="Users" />', ['points' => days(30)]);
    $busy = Blade::render('<x-admin.chart.sparkline :points="$points" label="Users" tone="coral" />', ['points' => array_map(fn (array $p, int $i): array => ['date' => $p['date'], 'count' => $i], days(30), range(0, 29))]);

    $window = __('admin.home.trends.window');
    expect($flat)->toContain('role="img"')->toContain("aria-label=\"Users, {$window}: 0\"")->toContain(__('admin.home.trends.window_short'))->toContain('stroke-dasharray')->toContain('sr-only')
        ->and(substr_count($flat, '<tr>'))->toBe(31)
        ->and($busy)->toContain("aria-label=\"Users, {$window}: 435\"")->toContain('vector-effect="non-scaling-stroke"')->toContain('var(--viz-coral');
});

it('renders the multi-series line chart with legend, zero-based axis and data table', function (): void {
    $html = Blade::render('<x-admin.chart.lines label="Trends" :series="$series" />', ['series' => [
        ['label' => 'Users', 'tone' => 'blue', 'field' => 'count', 'points' => array_map(fn (array $p, int $i): array => ['date' => $p['date'], 'count' => $i % 7], days(30), range(0, 29))],
        ['label' => 'Ads', 'tone' => 'coral', 'field' => 'count', 'points' => days(30, 'count', 3)],
    ]]);

    expect($html)->toContain('data-viz-lines=')->toContain('role="img"')->toContain('aria-label="Trends.')
        ->toContain('tabindex="0"')->toContain('aria-live="polite"')
        ->and(substr_count($html, '<path d="M'))->toBe(2)
        ->and(substr_count($html, '<tr>'))->toBe(31)
        ->and($html)->toContain('Users')->toContain('Ads')->toContain('>0</span>');
});

it('renders the line chart empty state instead of a flat chart when every value is zero', function (): void {
    $html = Blade::render('<x-admin.chart.lines label="Trends" :series="$series" />', ['series' => [
        ['label' => 'Users', 'tone' => 'blue', 'field' => 'count', 'points' => days(30)],
    ]]);

    expect($html)->toContain(__('admin.home.trends.none'))->not->toContain('role="group"');
});

it('formats money series without floats leaking into the labels', function (): void {
    $html = Blade::render('<x-admin.chart.lines money label="GMV" :series="$series" />', ['series' => [
        ['label' => 'GMV', 'tone' => 'violet', 'field' => 'gmv', 'points' => days(30, 'gmv', '12.50')],
    ]]);

    expect($html)->toContain('12.50')->toContain('data-viz-lines=');
});

it('renders the donut with a legend carrying every number, and a ring track when empty', function (): void {
    $slices = [
        ['label' => 'Active', 'value' => 6, 'tone' => 'success'],
        ['label' => 'Pending', 'value' => 3, 'tone' => 'warning'],
        ['label' => 'Rejected', 'value' => 1, 'tone' => 'danger'],
    ];
    $html = Blade::render('<x-admin.chart.donut :slices="$slices" label="Ads by status" caption="ads" />', ['slices' => $slices]);
    $empty = Blade::render('<x-admin.chart.donut :slices="[]" label="Ads by status" caption="ads" />');

    expect($html)->toContain('role="img"')->toContain('aria-label="Ads by status. Active: 6. Pending: 3. Rejected: 1"')
        ->toContain('60%')->toContain('30%')->toContain('10%')
        ->and(substr_count($html, 'data-slice='))->toBe(3)
        ->and($empty)->toContain('aria-label="Ads by status. ' . __('admin.home.trends.none') . '"')->not->toContain('data-slice=');
});

it('draws a lone donut slice as a full ring', function (): void {
    $html = Blade::render('<x-admin.chart.donut :slices="$slices" label="x" />', ['slices' => [['label' => 'Only', 'value' => 4, 'tone' => 'info']]]);

    expect($html)->toContain('100%')->and(substr_count($html, 'data-slice='))->toBe(1);
});

it('renders horizontal bars from zero with the value at the end and a table', function (): void {
    $rows = [['label' => 'Cars', 'value' => 40], ['label' => 'Phones', 'value' => 10], ['label' => 'Empty', 'value' => 0]];
    $html = Blade::render('<x-admin.chart.bars :rows="$rows" label="Top categories" unit="ads" />', ['rows' => $rows]);

    expect($html)->toContain('role="img"')->toContain('Top categories. Cars: 40. Phones: 10. Empty: 0')
        ->and($html)->toContain('width="100"')->toContain('width="25"')
        ->and(substr_count($html, 'fill="var(--viz-coral'))->toBe(2)
        ->and($html)->toContain('<th scope="row">Cars</th><td>40</td>')->toContain(__('admin.home.trends.total', ['total' => 50]));
});

it('renders the bar chart empty state', function (): void {
    expect(Blade::render('<x-admin.chart.bars :rows="[]" label="x" />'))->toContain(__('admin.home.empty'))
        ->and(Blade::render('<x-admin.chart.bars :rows="$rows" label="x" />', ['rows' => [['label' => 'a', 'value' => 0]]]))->toContain(__('admin.home.empty'));
});

it('renders the share meter, also with a zero total', function (): void {
    $half = Blade::render('<x-admin.chart.meter :value="5" :total="10" label="Pending" caption="of all ads" />');
    $none = Blade::render('<x-admin.chart.meter :value="0" :total="0" label="Pending" caption="of all ads" />');

    expect($half)->toContain('aria-label="Pending: 5 / 10 (50%)"')->toContain('width="50"')
        ->and($none)->toContain('aria-label="Pending: 0 / 0 (0%)"');
});

it('mirrors the charts in Arabic so the newest day sits at the start edge', function (): void {
    $points = array_map(fn (array $p, int $i): array => ['date' => $p['date'], 'count' => $i], days(30), range(0, 29));
    $render = fn (): string => Blade::render('<x-admin.chart.lines label="T" :series="$series" />', ['series' => [['label' => 'U', 'tone' => 'blue', 'field' => 'count', 'points' => $points]]]);

    app()->setLocale('en');
    $ltr = $render();
    app()->setLocale('ar');
    $rtl = $render();

    expect($ltr)->toContain('&quot;mirror&quot;:false')->and($rtl)->toContain('&quot;mirror&quot;:true');
});

// --- Review follow-ups -------------------------------------------------------------

it('starts every chart script after the document is parsed, so later charts are wired too', function (): void {
    $html = Blade::render('<x-admin.chart.lines label="A" :series="$a" /><x-admin.chart.lines label="B" :series="$a" /><x-admin.chart.donut :slices="$d" label="D1" /><x-admin.chart.donut :slices="$d" label="D2" />', [
        'a' => [['label' => 'U', 'tone' => 'blue', 'field' => 'count', 'points' => days(30, 'count', 2)]],
        'd' => [['label' => 'X', 'value' => 1, 'tone' => 'info']],
    ]);

    expect(substr_count($html, "document.readyState === 'loading'"))->toBe(2)
        ->and(substr_count($html, 'DOMContentLoaded'))->toBe(2)
        ->and(substr_count($html, '<script>'))->toBe(2)
        ->and(substr_count($html, 'data-viz-lines='))->toBe(2)
        ->and(substr_count($html, 'data-viz-donut'))->toBeGreaterThanOrEqual(2);
});

it('uses an M tier and money precision on money axes', function (): void {
    $big = Blade::render('<x-admin.chart.lines money label="G" :series="$s" />', ['s' => [['label' => 'G', 'tone' => 'violet', 'field' => 'gmv', 'points' => days(30, 'gmv', '3000000.00')]]]);
    $small = Blade::render('<x-admin.chart.lines money label="G" :series="$s" />', ['s' => [['label' => 'G', 'tone' => 'violet', 'field' => 'gmv', 'points' => days(30, 'gmv', '0.40')]]]);

    expect($big)->toContain('>3M</span>')->not->toContain('3,000K')
        ->and($small)->toContain('>0.40</span>')->toContain('>0.00</span>');
});

it('keeps raw colours out of the dashboard views and defines the chart tokens once', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));
    $files = [...glob(resource_path('views/admin/dashboard/*.php')), ...glob(resource_path('views/components/admin/chart/*.php')), ...glob(resource_path('views/components/admin/dashboard/*.php')), resource_path('views/admin/dashboard.blade.php')];

    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toMatch('/#[0-9a-fA-F]{6}|(emerald|amber|red)-\d{2,3}|<style/');
    }
    expect($css)->toContain('--viz-coral: var(--color-coral)')->and(Palette::css('coral'))->toBe('var(--viz-coral)');
});

it('never hands another role the finance series or distributions a super admin cached', function (): void {
    $service = app(DashboardStatsService::class);
    $service->forUser(chartStaff('super_admin'), DashboardPeriod::WEEK);

    $moderator = $service->forUser(chartStaff('moderator'), DashboardPeriod::WEEK);

    expect($moderator['series'])->not->toHaveKeys(['revenue', 'orders'])
        ->and($moderator['distributions'])->not->toHaveKey('orders')
        ->and($moderator)->not->toHaveKey('system');
});

it('gives finance.view alone the money charts but none of the finance queues', function (): void {
    $viewer = chartStaff('support');
    $viewer->givePermissionTo('finance.view');

    $stats = app(DashboardStatsService::class)->forUser($viewer, DashboardPeriod::WEEK);
    expect($stats['series'])->toHaveKey('revenue')
        ->and(array_column($stats['attention'], 'key'))->not->toContain('pending_settlements', 'pending_withdrawals', 'disputes');

    actingAs($viewer)->get(route('admin.dashboard'))->assertOk()
        ->assertSee(__('admin.home.trends.revenue'))
        ->assertDontSee(route('admin.finance.withdrawals.index', [], false), false);
});

it('shows system health only with settings.manage', function (): void {
    $manager = chartStaff('support');
    $manager->givePermissionTo('settings.manage');

    actingAs($manager)->get(route('admin.dashboard'))->assertOk()->assertSee(__('admin.home.system.title'));
    actingAs(chartStaff('moderator'))->get(route('admin.dashboard'))->assertOk()->assertDontSee(__('admin.home.system.title'));
});
