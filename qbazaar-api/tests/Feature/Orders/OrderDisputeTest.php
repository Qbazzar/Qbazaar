<?php

declare(strict_types=1);

use App\Enums\DisputeResolution;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PlatformSetting;
use App\Events\Orders\OrderDisputed;
use App\Exceptions\ErrorCode;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Finance\FinanceReviewRequestedNotification;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\ManagesMoney;
use Tests\Concerns\PlacesOrders;

uses(RefreshDatabase::class, CreatesAds::class, PlacesOrders::class, ManagesMoney::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seedReferenceData();
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    config(['qbazaar.commission.rate' => '5.00']);

    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

function handOver(User $seller, Order $order): Order
{
    test()->actingAs($seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-handover')->assertOk();

    return $order->refresh();
}

function reportProblem(User $actor, Order $order, string $reason = 'The phone does not turn on at all.')
{
    return test()->actingAs($actor, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/report-problem', ['reason' => $reason]);
}

function resolveDispute(User $admin, Order $order, DisputeResolution $ruling, string $note = 'Checked both sides\' photos.')
{
    return actingAs($admin)->post(route('admin.finance.disputes.resolve', $order), ['resolution' => $ruling->value, 'note' => $note]);
}

it('opens a dispute when the buyer reports a problem within the window after a cash handover', function (): void {
    Event::fake([OrderDisputed::class]);
    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00'));

    $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/orders/' . $order->id)
        ->assertOk()
        ->assertJsonPath('data.report_problem_until', $order->handed_over_at->copy()->addHours(48)->toIso8601String());

    reportProblem($this->buyer, $order)
        ->assertOk()
        ->assertJsonPath('data.status', 'disputed')
        ->assertJsonPath('data.dispute.reason', 'The phone does not turn on at all.')
        ->assertJsonPath('data.report_problem_until', null);

    expect($order->refresh()->status)->toBe(OrderStatus::DISPUTED)
        ->and($order->disputed_by)->toBe($this->buyer->id);

    Event::assertDispatched(OrderDisputed::class);
    Notification::assertSentTo($this->admin, FinanceReviewRequestedNotification::class);
});

it('closes the report window after the admin-set number of hours', function (): void {
    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00'));
    app(SettingsService::class)->put(PlatformSetting::ORDER_DISPUTE_WINDOW_HOURS, 2, $this->admin);

    $this->travel(2)->hours();
    $this->travel(1)->seconds();

    reportProblem($this->buyer, $order)
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::DISPUTE_WINDOW_CLOSED->value);

    expect($order->refresh()->status)->toBe(OrderStatus::COMPLETED);
});

it('accepts a report only from the buyer, only after the handover, and only once', function (): void {
    $waiting = $this->orderAwaitingHandover($this->seller, $this->buyer);
    reportProblem($this->buyer, $waiting)->assertStatus(422)->assertJsonPath('error.code', ErrorCode::DISPUTE_WINDOW_CLOSED->value);

    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, User::factory()->create(), '400.00'));
    $buyer = $order->buyer;
    reportProblem($this->seller, $order)->assertForbidden();
    reportProblem(User::factory()->create(), $order)->assertNotFound();

    reportProblem($buyer, $order)->assertOk();
    reportProblem($buyer, $order)->assertStatus(422)->assertJsonPath('error.code', ErrorCode::DISPUTE_WINDOW_CLOSED->value);
});

it('keeps the sale and its commission when the admin rules for the seller', function (): void {
    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00'));
    reportProblem($this->buyer, $order)->assertOk();

    resolveDispute($this->admin, $order, DisputeResolution::COMPLETED)->assertRedirect()->assertSessionHas('status');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::COMPLETED)
        ->and($order->dispute_resolution)->toBe(DisputeResolution::COMPLETED)
        ->and($order->dispute_resolution_note)->toBe('Checked both sides\' photos.')
        ->and($order->dispute_resolved_by)->toBe($this->admin->id)
        ->and(LedgerTransaction::query()->where('type', LedgerTransactionType::COMMISSION_CHARGED->value)->count())->toBe(1)
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('20.00')
        ->and(Activity::query()->where('log_name', 'orders')->where('event', 'dispute_resolved')->sole()->causer_id)->toBe($this->admin->id);
});

it('cancels the sale and gives the commission back when the admin rules for the buyer', function (): void {
    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00'));
    reportProblem($this->buyer, $order)->assertOk();

    resolveDispute($this->admin, $order, DisputeResolution::CANCELLED, 'Item not as described.')->assertRedirect()->assertSessionHas('status');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->cancellation_reason)->toBe('Item not as described.')
        ->and($order->dispute_resolution)->toBe(DisputeResolution::CANCELLED)
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('0.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_REVENUE))->toBe('0.00')
        ->and(LedgerTransaction::query()->where('type', LedgerTransactionType::COMMISSION_REFUNDED->value)->count())->toBe(1);
});

it('returns an already settled commission to the seller\'s wallet when the sale is cancelled', function (): void {
    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00'));
    $this->fundWallet($this->seller, '15.00');
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/settlements', ['method' => 'wallet', 'amount' => '15.00'])->assertCreated();
    reportProblem($this->buyer, $order)->assertOk();

    resolveDispute($this->admin, $order, DisputeResolution::CANCELLED)->assertRedirect();

    expect($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('0.00')
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('15.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_REVENUE))->toBe('0.00');
});

it('rules on a dispute once and never on an order that is not disputed', function (): void {
    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00'));
    resolveDispute($this->admin, $order, DisputeResolution::CANCELLED)->assertRedirect()->assertSessionHas('error');
    expect($order->refresh()->status)->toBe(OrderStatus::COMPLETED);

    reportProblem($this->buyer, $order)->assertOk();
    resolveDispute($this->admin, $order, DisputeResolution::COMPLETED)->assertSessionHas('status');
    resolveDispute($this->admin, $order, DisputeResolution::CANCELLED)->assertSessionHas('error');
    reportProblem($this->buyer, $order)->assertStatus(422);

    expect($order->refresh()->status)->toBe(OrderStatus::COMPLETED)
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('20.00');
});

it('lets only staff with finance.manage rule on disputes', function (): void {
    $order = handOver($this->seller, $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00'));
    reportProblem($this->buyer, $order)->assertOk();
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    resolveDispute($moderator, $order, DisputeResolution::CANCELLED)->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::DISPUTED);
    actingAs($this->admin)->get(route('admin.finance.disputes.index'))->assertOk()->assertSee('The phone does not turn on at all.');
});
