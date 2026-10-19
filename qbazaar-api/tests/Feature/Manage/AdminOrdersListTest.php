<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class, CountsPageQueries::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->admin = User::factory()->create()->assignRole('super_admin');
});

function listedOrder(array $attributes = [], OrderStatus $status = OrderStatus::CREATED): Order
{
    return Order::factory()->status($status)->create($attributes);
}

it('lists orders newest first with the parties, money and status, linking each order', function (): void {
    $this->travelTo(now()->subDay());
    $older = listedOrder(['ad_title' => 'Older camera']);
    $this->travelBack();
    $newer = listedOrder(['ad_title' => 'Newer phone', 'total' => '1250.00', 'commission_amount' => '62.50'], OrderStatus::COMPLETED);
    actingAs($this->admin);

    $this->get(route('admin.finance.orders.index'))
        ->assertOk()
        ->assertSeeInOrder(['Newer phone', 'Older camera'])
        ->assertSee(route('admin.finance.orders.show', $newer), false)
        ->assertSee($newer->buyer->full_name)
        ->assertSee($newer->seller->full_name)
        ->assertSee('1,250.00')
        ->assertSee(__('admin.deal.order_status.completed'));
});

it('filters by status', function (): void {
    listedOrder(['ad_title' => 'Disputed sofa'], OrderStatus::DISPUTED);
    listedOrder(['ad_title' => 'Fresh order']);
    actingAs($this->admin);

    $this->get(route('admin.finance.orders.index', ['status' => 'disputed']))
        ->assertOk()
        ->assertSee('Disputed sofa')
        ->assertDontSee('Fresh order');
});

it('finds one order by its id and ignores text that is not an id', function (): void {
    $wanted = listedOrder(['ad_title' => 'Wanted bike']);
    listedOrder(['ad_title' => 'Other bike']);
    actingAs($this->admin);

    $this->get(route('admin.finance.orders.index', ['q' => strtoupper($wanted->id)]))
        ->assertSee('Wanted bike')
        ->assertDontSee('Other bike');

    $this->get(route('admin.finance.orders.index', ['q' => 'bike']))
        ->assertSee(__('admin.orders_admin.search_ignored'))
        ->assertSee('Wanted bike')
        ->assertSee('Other bike');
});

it('is open to finance viewers only', function (string $role): void {
    actingAs(User::factory()->create()->assignRole($role));

    $this->get(route('admin.finance.orders.index'))->assertForbidden();
})->with(['moderator', 'support']);

it('shows the orders link in the finance menu', function (): void {
    actingAs($this->admin);

    $this->get(route('admin.dashboard'))->assertSee(route('admin.finance.orders.index'), false);
});

it('keeps its query count flat as orders and avatars grow', function (): void {
    actingAs($this->admin);
    $uri = route('admin.finance.orders.index');
    listedOrder();
    $few = $this->steadyQueryCount($uri);

    foreach (range(1, 15) as $i) {
        $order = listedOrder([], OrderStatus::cases()[$i % 5]);
        if ($i % 2 === 0) {
            $order->buyer->addMedia(UploadedFile::fake()->image('a.jpg', 20, 20))->toMediaCollection('avatar');
        }
    }

    expect($this->steadyQueryCount($uri))->toBe($few)
        ->and($this->steadyQueryCount($uri . '?status=completed'))->toBeLessThanOrEqual($few);
});
