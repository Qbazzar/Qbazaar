<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerReferenceType;
use App\Enums\OrderStatus;
use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\Revenue\RevenueRange;
use App\Services\Finance\Revenue\RevenueReportService;
use App\Services\Finance\Revenue\RevenueWindow;
use App\Services\Ledger\LedgerRecipes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CreatesAds;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, ManagesMoney::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    Cache::flush();
    app()->setLocale('ar');
    Carbon::setTestNow('2026-10-13 09:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->seller = User::factory()->create(['full_name' => 'نورة البائعة']);
});

afterEach(fn () => Carbon::setTestNow());

/** A completed cash order whose commission the ledger charged, posted at $at. */
function revenueCommission(User $seller, string $amount, string $at = '2026-10-12 10:00:00', ?Ad $ad = null): Order
{
    Carbon::setTestNow($at);
    $order = Order::factory()->create([
        'ad_id' => $ad?->id,
        'seller_id' => $seller->id,
        'status' => OrderStatus::COMPLETED->value,
        'completed_at' => $at,
        'total' => '200.00',
        'commission_amount' => $amount,
    ]);
    app(LedgerRecipes::class)->chargeCommission($order, LedgerActor::system());
    Carbon::setTestNow('2026-10-13 09:00:00');

    return $order;
}

function revenuePromotion(string $amount, string $at = '2026-10-12 11:00:00'): void
{
    Carbon::setTestNow($at);
    app(LedgerRecipes::class)->purchasePromotionByBankTransfer(
        $amount,
        new LedgerReference(LedgerReferenceType::PROMOTION, (string) Str::ulid()),
        LedgerActor::system(),
    );
    Carbon::setTestNow('2026-10-13 09:00:00');
}

/** Runs a ledger action as of $at, since a period ends at "now" and excludes what is posted at that very moment. */
function revenueAt(string $at, Closure $action): void
{
    Carbon::setTestNow($at);
    $action();
    Carbon::setTestNow('2026-10-13 09:00:00');
}

function revenueWindow(RevenueRange $range = RevenueRange::MONTH): RevenueWindow
{
    return $range->window(CarbonImmutable::now('Asia/Qatar'));
}

describe('income statement', function (): void {
    it('sums commission and promotions from the ledger and nets out refunded commission', function (): void {
        $order = revenueCommission($this->seller, '10.00');
        revenueCommission($this->seller, '5.50');
        revenuePromotion('25.00');
        revenueAt('2026-10-12 12:00:00', fn () => app(LedgerRecipes::class)->refundCommission($order, LedgerActor::system()));

        $report = app(RevenueReportService::class)->report(revenueWindow(), true);

        expect($report->current->income['commission'])->toBe('15.50')
            ->and($report->current->income['promotions'])->toBe('25.00')
            ->and($report->current->totalIncome())->toBe('40.50')
            ->and($report->current->refunds)->toBe('10.00')
            ->and($report->current->netProfit())->toBe('30.50')
            ->and($report->current->gmv)->toBe('400.00')
            ->and($report->current->orders)->toBe(2);
    });

    it('marks what the ledger does not record yet as null instead of zero', function (): void {
        $report = app(RevenueReportService::class)->report(revenueWindow(), true);

        expect($report->current->income['subscriptions'])->toBeNull()
            ->and($report->current->gatewayFees)->toBeNull()
            ->and($report->current->totalIncome())->toBe('0.00');
    });

    it('keeps wallet adjustments out of operating net profit and reports them apart', function (): void {
        revenueCommission($this->seller, '20.00');
        revenueAt('2026-10-12 12:00:00', fn () => $this->fundWallet($this->seller, '8.00'));

        $current = app(RevenueReportService::class)->report(revenueWindow(), true)->current;

        expect($current->netProfit())->toBe('20.00')
            ->and($current->totalDeductions())->toBe('0.00')
            ->and($current->walletAdjustments)->toBe('8.00')
            ->and($current->netAfterAdjustments())->toBe('12.00');
    });

    it('does not let demo-style wallet top-ups change operating net profit', function (): void {
        revenueCommission($this->seller, '20.00');
        $before = app(RevenueReportService::class)->report(revenueWindow(), true)->current->netProfit();

        Cache::flush();
        foreach (range(1, 5) as $i) {
            revenueAt('2026-10-12 12:0' . $i . ':00', fn () => $this->fundWallet($this->seller, '50.00'));
        }
        $after = app(RevenueReportService::class)->report(revenueWindow(), true)->current;

        expect($after->netProfit())->toBe($before)->and($after->walletAdjustments)->toBe('250.00');
    });

    it('shows a net clawback as recovered and never as extra profit', function (): void {
        revenueCommission($this->seller, '20.00');
        revenueAt('2026-09-01 10:00:00', fn () => $this->fundWallet($this->seller, '30.00'));
        revenueAt('2026-10-12 12:00:00', fn () => app(LedgerRecipes::class)->adjustWallet(
            $this->seller->id,
            '-5.00',
            'clawback',
            new LedgerReference(LedgerReferenceType::ADJUSTMENT, (string) Str::ulid()),
            LedgerActor::system(),
        ));

        $current = app(RevenueReportService::class)->report(revenueWindow(), true)->current;

        expect($current->walletAdjustments)->toBe('-5.00')->and($current->netProfit())->toBe('20.00');
        actingAs($this->admin)->get(route('admin.finance.revenue.index'))->assertOk()->assertSee('تعديلات المحافظ (مسترد)');
    });

    it('compares with the previous period and keeps escrow out of income', function (): void {
        revenueCommission($this->seller, '10.00', '2026-10-12 10:00:00');
        revenueCommission($this->seller, '4.00', '2026-09-05 10:00:00');
        $order = Order::factory()->create(['ad_id' => null, 'seller_id' => $this->seller->id, 'total' => '60.00', 'commission_amount' => '3.00']);
        app(LedgerRecipes::class)->receiveOrderPayment($order, LedgerAccountType::PLATFORM_BANK, LedgerActor::system());

        $report = app(RevenueReportService::class)->report(revenueWindow(), true);

        expect($report->current->income['commission'])->toBe('10.00')
            ->and($report->previous->income['commission'])->toBe('4.00')
            ->and($report->escrowHeld)->toBe('60.00')
            ->and($report->current->totalIncome())->toBe('10.00');
    });

    it('buckets the series per day with a point for every day', function (): void {
        revenueCommission($this->seller, '10.00', '2026-10-12 10:00:00');

        $series = app(RevenueReportService::class)->report(revenueWindow(), true)->series;

        expect($series)->toHaveCount(30)
            ->and(collect($series)->firstWhere('date', '2026-10-12')['income'])->toBe('10.00')
            ->and(collect($series)->firstWhere('date', '2026-10-11')['income'])->toBe('0.00');
    });

    it('reads the income of one order for the deal panel', function (): void {
        $order = revenueCommission($this->seller, '12.00');

        $income = app(RevenueReportService::class)->platformIncomeForOrder($order->id);

        expect($income->commission)->toBe('12.00')->and($income->net())->toBe('12.00')->and($income->isRecorded())->toBeTrue();
        expect(app(RevenueReportService::class)->platformIncomeForOrder((string) Str::ulid())->isRecorded())->toBeFalse();
    });
});

describe('the page', function (): void {
    it('renders the figures, the unrecorded notes and the tables for finance staff', function (): void {
        revenueCommission($this->seller, '10.00');
        revenuePromotion('25.00');
        actingAs($this->admin);

        $html = $this->get(route('admin.finance.revenue.index'))
            ->assertOk()
            ->assertSee('data-qb-page', false)
            ->assertSee('العوائد والأرباح')
            ->assertSee('35.00 QAR')
            ->assertSee('غير مسجّل بعد')
            ->assertSee('نورة البائعة')
            ->assertSee('data-viz-lines', false)
            ->assertSee('data-viz-donut', false)
            ->assertSee(route('admin.finance.revenue.export', ['period' => '30d']), false)
            ->getContent();

        expect(substr_count($html, '<h1'))->toBe(1);
    });

    it('shows an empty state when the ledger has no revenue yet', function (): void {
        actingAs($this->admin)->get(route('admin.finance.revenue.index'))
            ->assertOk()
            ->assertSee('لا توجد حركات إيرادات في هذه الفترة.');
    });

    it('rejects a reversed, future or over-long custom range', function (string $from, string $to, string $field): void {
        actingAs($this->admin)
            ->from(route('admin.finance.revenue.index'))
            ->get(route('admin.finance.revenue.index', ['period' => 'custom', 'from' => $from, 'to' => $to]))
            ->assertRedirect(route('admin.finance.revenue.index'))
            ->assertSessionHasErrors($field);
    })->with([
        'from after to' => ['2026-10-10', '2026-10-01', 'to'],
        'in the future' => ['2026-11-01', '2026-11-05', 'from'],
        'longer than two years' => ['2024-01-01', '2026-10-01', 'to'],
    ]);

    it('accepts a valid custom range and the other presets', function (string $query): void {
        actingAs($this->admin)->get(route('admin.finance.revenue.index') . '?' . $query)->assertOk();
    })->with(['period=custom&from=2026-09-01&to=2026-09-30', 'period=today', 'period=7d', 'period=this_month', 'period=last_month', 'period=this_year']);
});

it('attributes income to the category of the ad behind the order', function (): void {
    $this->seedReferenceData();
    $ad = Ad::factory()->create();
    revenueCommission($this->seller, '10.00', '2026-10-12 10:00:00', $ad);

    $categories = app(RevenueReportService::class)->report(revenueWindow(), true)->categories;

    expect($categories)->toHaveCount(1)->and($categories[0]['id'])->toBe($ad->category_id)->and($categories[0]['income'])->toBe('10.00');
});

describe('authorisation', function (): void {
    it('lets only finance.view roles in and shows the sidebar link to them alone', function (string $role, bool $allowed): void {
        $staff = User::factory()->create()->assignRole($role);
        actingAs($staff);
        $link = route('admin.finance.revenue.index');

        $page = $this->get(route('admin.dashboard'))->assertOk();
        $allowed ? $page->assertSee($link, false) : $page->assertDontSee($link, false);

        $this->get($link)->assertStatus($allowed ? 200 : 403);
        $this->get(route('admin.finance.revenue.export'))->assertStatus($allowed ? 200 : 403);
    })->with([
        'super_admin' => ['super_admin', true],
        'moderator' => ['moderator', false],
        'support' => ['support', false],
    ]);
});

describe('csv export', function (): void {
    it('streams the revenue lines of the period with a byte-order mark and neutralised formulas', function (): void {
        $this->seedReferenceData();
        $ad = Ad::factory()->create(['title' => '=HYPERLINK("x")']);
        $order = revenueCommission($this->seller, '10.00', '2026-10-12 10:00:00', $ad);
        $order->forceFill(['ad_title' => '=HYPERLINK("x")'])->save();
        revenuePromotion('25.00');

        $response = actingAs($this->admin)->get(route('admin.finance.revenue.export', ['period' => '30d']));
        $csv = $response->streamedContent();

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        expect($csv)->toStartWith("\xEF\xBB\xBF")
            ->and(substr_count(trim($csv), "\n"))->toBe(4)
            ->and($csv)->toContain('35.00')
            ->and($csv)->toContain("'=HYPERLINK")
            ->and($csv)->toContain('10.00')->toContain('25.00');
    });
});

describe('performance', function (): void {
    function revenueQueryCount(): int
    {
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->get(route('admin.finance.revenue.index', ['period' => 'this_year']))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    it('runs the same number of queries however many ledger rows the period holds', function (): void {
        revenueCommission($this->seller, '5.00');
        actingAs($this->admin);
        revenueQueryCount();
        $few = revenueQueryCount();

        foreach (range(1, 40) as $i) {
            revenueCommission($this->seller, '5.00', '2026-10-' . sprintf('%02d', 1 + $i % 12) . ' 10:00:00');
            revenuePromotion('3.00', '2026-09-' . sprintf('%02d', 1 + $i % 20) . ' 10:00:00');
        }
        $many = revenueQueryCount();

        expect($many)->toBe($few)->and($many)->toBeLessThanOrEqual(40);
    });

    it('serves a repeated request from the cache', function (): void {
        revenueCommission($this->seller, '5.00');
        actingAs($this->admin);
        $this->get(route('admin.finance.revenue.index'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(RevenueReportService::class)->report(revenueWindow(), true);
        DB::disableQueryLog();

        expect(DB::getQueryLog())->toBe([]);
    });
});
