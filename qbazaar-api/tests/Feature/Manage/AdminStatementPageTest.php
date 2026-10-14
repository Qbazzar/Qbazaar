<?php

declare(strict_types=1);

use App\Enums\SettlementStatus;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, ManagesMoney::class, CountsPageQueries::class);

beforeEach(function (): void {
    $this->withoutVite();
    Notification::fake();
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->seller = User::factory()->create(['full_name' => 'نورة البائعة', 'phone' => '+97455123456']);
});

/** Ledger movements plus settlements and withdrawals of the seller. */
function giveStatementHistory(User $seller, int $count): void
{
    foreach (range(1, $count) as $i) {
        test()->fundWallet($seller, '10.00');
        // A seller has at most one pending settlement, so the history is reviewed ones.
        test()->settlementOf($seller, $i % 2 === 0 ? SettlementStatus::REJECTED : SettlementStatus::APPROVED);
        test()->withdrawalOf($seller, WithdrawalStatus::PAID);
    }
}

it('renders in the shell for finance staff with one h1, the balances and the owner card', function (): void {
    $this->fundWallet($this->seller, '110.00');
    actingAs($this->admin);

    $html = $this->get(route('admin.finance.statements.show', $this->seller))
        ->assertOk()
        ->assertSee('data-qb-page', false)
        ->assertSee('max-w-[96rem]', false)
        ->assertSee('كشف حساب نورة البائعة')
        ->assertSee('الرصيد المتاح')
        ->assertSee('110.00 QAR')
        ->assertSee('حركات الحساب')
        ->assertSee('+974 5512 3456')
        ->assertSee(route('admin.users.show', $this->seller), false)
        ->assertSee(route('admin.finance.settlements.index'), false)
        ->assertSee(route('admin.finance.withdrawals.index'), false)
        ->getContent();

    expect(substr_count($html, '<h1'))->toBe(1);
});

it('shows the empty ledger and empty side lists for a user without movements', function (): void {
    actingAs($this->admin);

    $this->get(route('admin.finance.statements.show', $this->seller))
        ->assertOk()
        ->assertSee('لا توجد حركات.')
        ->assertSee('لا توجد تسويات.')
        ->assertSee('لا توجد طلبات سحب.');
});

it('pages a long ledger', function (): void {
    config(['qbazaar.wallet.statement_per_page' => 5]);
    giveStatementHistory($this->seller, 7);
    actingAs($this->admin);

    $html = $this->get(route('admin.finance.statements.show', $this->seller))->assertOk()->getContent();

    expect($html)->toContain('cursor=');
});

it('lists only the three newest settlements and withdrawals of this user', function (): void {
    giveStatementHistory($this->seller, 5);
    giveStatementHistory(User::factory()->create(), 2);
    actingAs($this->admin);

    $html = $this->get(route('admin.finance.statements.show', $this->seller))->assertOk()->getContent();

    // Three rows in each of the two side cards; every settlement and withdrawal is 10.00.
    expect(substr_count($html, '<bdi dir="ltr">10.00 QAR</bdi>'))->toBe(6);
});

it('drops the user links for finance staff who cannot open users', function (): void {
    Role::findByName('support', 'web')->syncPermissions(['finance.view']);
    actingAs(User::factory()->create()->assignRole('support'));

    $html = $this->get(route('admin.finance.statements.show', $this->seller))
        ->assertOk()
        ->assertSee('نورة البائعة')
        ->assertDontSee(route('admin.users.show', $this->seller), false)
        ->getContent();

    expect(substr_count($html, '<h1'))->toBe(1);
});

it('refuses the statement to staff without finance.view', function (): void {
    actingAs(User::factory()->create()->assignRole('moderator'));

    $this->get(route('admin.finance.statements.show', $this->seller))->assertForbidden();
});

it('keeps the query count flat with 1 or 25 movements, settlements and withdrawals', function (): void {
    actingAs($this->admin);
    $uri = route('admin.finance.statements.show', $this->seller);

    giveStatementHistory($this->seller, 1);
    $few = $this->steadyQueryCount($uri);

    giveStatementHistory($this->seller, 24);
    $many = $this->steadyQueryCount($uri);

    expect($many)->toBe($few)->toBeLessThanOrEqual(10);
});

it('prints the configured currency and links the side lists to this user only', function (): void {
    config(['qbazaar.default_currency' => 'USD']);
    giveStatementHistory($this->seller, 2);
    actingAs($this->admin);

    $this->get(route('admin.finance.statements.show', $this->seller))
        ->assertOk()
        ->assertSee('<bdi dir="ltr">10.00 USD</bdi>', false)
        ->assertDontSee('<bdi dir="ltr">10.00 QAR</bdi>', false)
        ->assertSee(route('admin.finance.settlements.index', ['user' => $this->seller->id]), false)
        ->assertSee(route('admin.finance.withdrawals.index', ['user' => $this->seller->id]), false);
});

it('narrows the settlement and withdrawal queues to one user across every status', function (): void {
    $stranger = User::factory()->create(['full_name' => 'غريب عن الكشف']);
    test()->settlementOf($this->seller, SettlementStatus::APPROVED);
    test()->withdrawalOf($this->seller, WithdrawalStatus::PAID);
    test()->settlementOf($stranger, SettlementStatus::APPROVED);
    test()->withdrawalOf($stranger, WithdrawalStatus::PAID);
    actingAs($this->admin);

    foreach (['admin.finance.settlements.index', 'admin.finance.withdrawals.index'] as $route) {
        $this->get(route($route, ['user' => $this->seller->id]))
            ->assertOk()
            ->assertSee('نورة البائعة')
            ->assertDontSee('غريب عن الكشف')
            ->assertSee('user=' . $this->seller->id, false);
    }
});
