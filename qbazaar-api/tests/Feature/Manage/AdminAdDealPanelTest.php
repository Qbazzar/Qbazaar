<?php

declare(strict_types=1);

use App\Data\Admin\AdDeal\OrderDeal;
use App\Data\Admin\AdDeal\PaymentSummary;
use App\Data\Ledger\LedgerActor;
use App\Enums\AdStatus;
use App\Enums\LedgerAccountType;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Orders\OrderTransitionService;
use App\Support\Money;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\PlacesOrders;

uses(RefreshDatabase::class, CreatesAds::class, PlacesOrders::class, CountsPageQueries::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->seller = User::factory()->create(['full_name' => 'محمد الكعبي']);
    $this->buyer = User::factory()->create(['full_name' => 'سارة المهندي', 'phone' => '+97455987654']);
    $this->admin = User::factory()->create()->assignRole('super_admin');
});

/** A sold ad with the whole chain: accepted offer, chat, order handed over, commission charged. */
function soldAdWithDeal(): array
{
    $test = test();
    $ad = $test->makeAd($test->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now(), 'title' => 'سيارة تويوتا كورولا']);
    $offer = $test->pendingOffer($test->seller, $test->buyer, $ad, '200.00');
    Message::factory()->create(['conversation_id' => $offer->conversation_id, 'sender_id' => $test->buyer->id, 'body' => 'هل ما زالت متاحة؟']);
    Conversation::query()->whereKey($offer->conversation_id)->update(['last_message_preview' => 'هل ما زالت متاحة؟', 'last_message_at' => now()]);
    $order = $test->acceptOffer($offer);
    $transitions = app(OrderTransitionService::class);
    $transitions->awaitHandover($order, PaymentMethod::CASH);
    $transitions->complete($order, LedgerActor::system());

    return [$ad->refresh(), $order->refresh(), $offer->refresh()];
}

/** Only the three seeded roles are staff, so a finance-style user is support plus the permissions asked for. */
function staffWith(array $permissions = [], array $without = []): User
{
    Role::findByName('support', 'web')->revokePermissionTo($without);

    return User::factory()->create()->assignRole('support')->givePermissionTo($permissions);
}

describe('super admin on a sold ad', function (): void {
    it('shows the buyer, the order, the payment, the chat, the offer and the timeline with working links', function (): void {
        [$ad, $order, $offer] = soldAdWithDeal();
        actingAs($this->admin);

        $html = $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee('id="ad-buyer-title"', false)
            ->assertSee('سارة المهندي')
            ->assertSee('+974 5598 7654')
            ->assertSee('id="ad-order-title"', false)
            ->assertSee(__('admin.deal.order_status.completed'))
            ->assertSee('id="ad-payment-title"', false)
            ->assertSee(__('admin.deal.ledger.commission_charged'))
            ->assertSee(__('admin.deal.payment.escrow_state.none'))
            ->assertSee('id="ad-conversations-title"', false)
            ->assertSee(__('chat.offer.accepted'))
            ->assertSee('id="ad-offers-title"', false)
            ->assertSee(OfferStatus::ACCEPTED->label()['ar'])
            ->assertSee('id="ad-deal-timeline-title"', false)
            ->assertSee(route('admin.users.show', $this->buyer), false)
            ->assertSee(route('admin.finance.orders.show', $order), false)
            ->assertSee(route('admin.finance.statements.show', $this->seller), false)
            ->assertSee(route('admin.finance.statements.show', $this->buyer), false)
            ->assertSee(route('admin.conversations.show', $offer->conversation_id), false)
            ->assertDontSee(__('admin.deal.buyer.likely'))
            ->getContent();

        expect(substr_count($html, '<h1'))->toBe(1);
    });

    it('reads the money from the order, not from Blade', function (): void {
        [$ad, $order] = soldAdWithDeal();
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(Money::format($order->total), false)
            ->assertSee(Money::format(Money::subtract($order->total, $order->commission_amount)), false);
    });

    it('shows a released escrow with when and by whom, and the seller wallet payout', function (): void {
        [$ad, $order] = soldAdWithDeal();
        $recipes = app(LedgerRecipes::class);
        $recipes->receiveOrderPayment($order, LedgerAccountType::PLATFORM_BANK, LedgerActor::system());
        $recipes->releaseEscrow($order, LedgerActor::admin($this->admin));
        $this->admin->forceFill(['full_name' => 'أحمد المالي'])->save();
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.payment.escrow_state.released'))
            ->assertSee(__('admin.deal.payment.payout_state.released'))
            ->assertSee(__('admin.deal.ledger.escrow_released'))
            ->assertSee('أحمد المالي');
    });

    it('shows a refunded escrow', function (): void {
        [$ad, $order] = soldAdWithDeal();
        $recipes = app(LedgerRecipes::class);
        $recipes->receiveOrderPayment($order, LedgerAccountType::PLATFORM_BANK, LedgerActor::system());
        $recipes->refundOrderPayment($order, LedgerActor::system());
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.payment.status.refunded'))
            ->assertSee(__('admin.deal.payment.escrow_state.refunded'));
    });

    it('tracks an order cancelled after handover as failed, with its reason', function (): void {
        [$ad, $order] = soldAdWithDeal();
        $order->forceFill(['status' => OrderStatus::CANCELLED, 'cancelled_at' => now(), 'cancellation_reason' => 'غيّرت رأيي'])->save();
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.order_status.cancelled'))
            ->assertSee(__('admin.deal.steps.failed'))
            ->assertSee('غيّرت رأيي');
    });

    it('says so when the ad was sold without a platform order', function (): void {
        $ad = $this->makeAd($this->seller, ['status' => AdStatus::SOLD->value]);
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.buyer.off_platform'))
            ->assertSee(__('admin.deal.order.none'))
            ->assertDontSee('id="ad-payment-title"', false);
    });

    it('labels the buyer of an accepted offer as likely when no order exists', function (): void {
        $ad = $this->makeAd($this->seller, ['status' => AdStatus::SOLD->value]);
        $offer = $this->pendingOffer($this->seller, $this->buyer, $ad, '150.00');
        $offer->forceFill(['status' => OfferStatus::ACCEPTED, 'accepted_at' => now()])->save();
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.buyer.likely'))
            ->assertSee('سارة المهندي')
            ->assertSee(__('admin.deal.buyer.off_platform'));
    });

    it('keeps an ordinary ad free of the sold sections, but lists its chats and offers', function (): void {
        $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
        $this->pendingOffer($this->seller, $this->buyer, $ad, '80.00');
        $ad->loadCount('conversations');
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertDontSee('id="ad-buyer-title"', false)
            ->assertDontSee('id="ad-order-title"', false)
            ->assertDontSee('id="ad-payment-title"', false)
            ->assertSee('id="ad-conversations-title"', false)
            ->assertSee('id="ad-offers-title"', false);
    });

    it('bounds the lists to five and links the rest to a filtered index', function (): void {
        [$ad] = soldAdWithDeal();
        foreach (range(1, 7) as $i) {
            $this->pendingOffer($this->seller, User::factory()->create(), $ad, (string) (100 + $i) . '.00');
        }
        actingAs($this->admin);

        $html = $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(route('admin.conversations.index', ['ad' => $ad->id]), false)
            ->assertSee(route('admin.offers.index', ['ad' => $ad->id]), false)
            ->getContent();

        $offers = (string) str($html)->after('id="ad-offers-title"')->before('</section>');
        $chats = (string) str($html)->after('id="ad-conversations-title"')->before('</section>');

        expect(substr_count($offers, '<tr'))->toBe(1 + 5)
            ->and(substr_count($chats, '<tr'))->toBe(1 + 5);
    });
});

describe('permissions per section', function (): void {
    it('lets the moderator see chats, offers and the likely buyer, but no order, payment or finance link', function (): void {
        [$ad, $order, $offer] = soldAdWithDeal();
        actingAs(User::factory()->create()->assignRole('moderator'));

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee('id="ad-buyer-title"', false)
            ->assertSee(__('admin.deal.buyer.likely'))
            ->assertSee('id="ad-conversations-title"', false)
            ->assertSee('id="ad-offers-title"', false)
            ->assertSee(route('admin.conversations.show', $offer->conversation_id), false)
            ->assertDontSee('id="ad-order-title"', false)
            ->assertDontSee('id="ad-payment-title"', false)
            ->assertDontSee('id="ad-deal-timeline-title"', false)
            ->assertDontSee('/admin/finance/', false)
            ->assertDontSee(__('admin.deal.buyer.off_platform'));
    });

    it('shows support no deal data at all', function (): void {
        [$ad] = soldAdWithDeal();
        actingAs(User::factory()->create()->assignRole('support'));

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee('id="ad-seller-title"', false)
            ->assertDontSee('id="ad-buyer-title"', false)
            ->assertDontSee('id="ad-order-title"', false)
            ->assertDontSee('id="ad-payment-title"', false)
            ->assertDontSee('id="ad-conversations-title"', false)
            ->assertDontSee('id="ad-offers-title"', false)
            ->assertDontSee('سارة المهندي')
            ->assertDontSee('هل ما زالت متاحة؟')
            ->assertDontSee('/admin/finance/', false)
            ->assertDontSee('/admin/conversations', false);
    });

    it('gives a finance-only role the order, payment and timeline but no chats or offers', function (): void {
        [$ad, $order, $offer] = soldAdWithDeal();
        actingAs(staffWith(['finance.view']));

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee('id="ad-buyer-title"', false)
            ->assertSee('id="ad-order-title"', false)
            ->assertSee('id="ad-payment-title"', false)
            ->assertSee('id="ad-deal-timeline-title"', false)
            ->assertSee(route('admin.finance.orders.show', $order), false)
            ->assertDontSee('id="ad-conversations-title"', false)
            ->assertDontSee('id="ad-offers-title"', false)
            ->assertDontSee('هل ما زالت متاحة؟')
            ->assertDontSee(route('admin.conversations.show', $offer->conversation_id), false);
    });

    it('hides the buyer profile from staff who cannot open users', function (): void {
        [$ad] = soldAdWithDeal();
        actingAs(staffWith(['finance.view'], without: ['users.view']));

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertDontSee('id="ad-buyer-title"', false)
            ->assertDontSee(route('admin.users.show', $this->buyer), false)
            ->assertSee('id="ad-order-title"', false);
    });

    it('opens the order page for finance staff only', function (): void {
        [, $order] = soldAdWithDeal();

        actingAs($this->admin)->get(route('admin.finance.orders.show', $order))
            ->assertOk()
            ->assertSee('data-qb-page', false)
            ->assertSee('id="ad-order-title"', false)
            ->assertSee('id="ad-payment-title"', false)
            ->assertSee('سارة المهندي')
            ->assertSee('محمد الكعبي');
        actingAs(staffWith(['finance.view']))->get(route('admin.finance.orders.show', $order))->assertOk();
        actingAs(User::factory()->create()->assignRole('moderator'))->get(route('admin.finance.orders.show', $order))->assertForbidden();
        actingAs(User::factory()->create()->assignRole('support'))->get(route('admin.finance.orders.show', $order))->assertForbidden();
    });

    it('never prints secrets or card data on the deal panel', function (): void {
        [$ad] = soldAdWithDeal();
        actingAs($this->admin);

        $response = $this->get(route('admin.ads.show', $ad))->assertOk();

        foreach (['iban', 'gateway_ref', 'card_number', 'cvv', 'api_key', 'secret'] as $needle) {
            $response->assertDontSee($needle, false);
        }

        foreach ([OrderDeal::class, PaymentSummary::class] as $class) {
            $properties = array_map(fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass($class))->getProperties());

            expect(preg_grep('/token|secret|card|gateway|iban|password/i', $properties))->toBeEmpty();
        }
    });

    it('treats a sold ad whose only order was cancelled as sold outside the platform', function (): void {
        $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
        $order = $this->placeOrder($this->seller, $this->buyer, '90.00', $ad);
        app(OrderTransitionService::class)->cancel($order, LedgerActor::system(), $this->buyer->id, 'سبب');
        $ad->refresh()->forceFill(['status' => AdStatus::SOLD])->save();
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.buyer.off_platform'))
            ->assertSee(__('admin.deal.order.past_orders', ['count' => 1]))
            ->assertDontSee('id="ad-payment-title"', false)
            ->assertDontSee(route('admin.finance.statements.show', $this->buyer), false);
    });

    it('shows a handed-over cash order cancelled by a ruling as paid then reversed', function (): void {
        [$ad, $order] = soldAdWithDeal();
        app(LedgerRecipes::class)->refundCommission($order, LedgerActor::system());
        $order->forceFill(['status' => OrderStatus::CANCELLED, 'cancelled_at' => now()])->save();
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.payment.status.reversed'))
            ->assertSee(__('admin.deal.payment.payout_state.reversed'))
            ->assertSee(__('admin.deal.ledger.commission_refunded'))
            ->assertDontSee(__('admin.deal.payment.status.not_charged'));
    });

    it('labels the seller commission debt as the seller total, outside the order figures', function (): void {
        [$ad] = soldAdWithDeal();
        actingAs($this->admin);

        $this->get(route('admin.ads.show', $ad))
            ->assertOk()
            ->assertSee(__('admin.deal.payment.seller_debt'));
    });

    it('shows finance-only staff the order page with the parties, as the statement page does', function (): void {
        [, $order] = soldAdWithDeal();
        actingAs(staffWith(['finance.view'], without: ['users.view']));

        $this->get(route('admin.finance.orders.show', $order))
            ->assertOk()
            ->assertSee($this->buyer->email)
            ->assertSee('سارة المهندي')
            ->assertSee('id="ad-payment-title"', false)
            ->assertDontSee(route('admin.users.show', $this->buyer), false);
    });
});

describe('ad filter on the lists', function (): void {
    it('ignores an ad filter that is not an id', function (): void {
        soldAdWithDeal();
        actingAs($this->admin);

        $this->get(route('admin.conversations.index', ['ad' => "x' OR 1=1"]))
            ->assertOk()
            ->assertSee('سيارة تويوتا كورولا')
            ->assertDontSee(__('admin.deal.filtered_by_ad'))
            ->assertDontSee('name="ad"', false);
        $this->get(route('admin.offers.index', ['ad' => 'nope']))->assertOk()->assertDontSee(__('admin.deal.filtered_by_ad'));
    });

    it('scopes conversations and offers to one ad and keeps the filter while searching', function (): void {
        [$ad] = soldAdWithDeal();
        $other = $this->makeAd($this->seller, ['title' => 'إعلان آخر مختلف', 'status' => AdStatus::ACTIVE->value]);
        $this->pendingOffer($this->seller, User::factory()->create(), $other, '50.00');
        actingAs($this->admin);

        $this->get(route('admin.conversations.index', ['ad' => $ad->id]))
            ->assertOk()
            ->assertSee('سيارة تويوتا كورولا')
            ->assertDontSee('إعلان آخر مختلف')
            ->assertSee('name="ad"', false)
            ->assertSee(__('admin.deal.filtered_by_ad'));
        $this->get(route('admin.offers.index', ['ad' => $ad->id]))
            ->assertOk()
            ->assertSee('سيارة تويوتا كورولا')
            ->assertDontSee('إعلان آخر مختلف');
        $this->get(route('admin.offers.index'))->assertOk()->assertSee('إعلان آخر مختلف');
    });
});

describe('performance', function (): void {
    it('keeps the query count flat as conversations, offers and ledger rows grow', function (): void {
        [$ad, $order] = soldAdWithDeal();
        actingAs($this->admin);
        $uri = route('admin.ads.show', $ad);

        $before = $this->steadyQueryCount($uri);

        foreach (range(1, 12) as $i) {
            $this->pendingOffer($this->seller, User::factory()->create(), $ad, (string) (100 + $i) . '.00');
            DB::table('ledger_transactions')->insert([
                'id' => (string) Str::ulid(),
                'type' => 'adjustment',
                'idempotency_key' => 'budget-' . $i . '-' . Str::random(8),
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'created_by_type' => 'system',
                'posted_at' => now()->subMinutes($i),
                'created_at' => now(),
            ]);
        }

        expect($this->steadyQueryCount($uri))->toBe($before);
    });

    it('serves the new lookups from indexes', function (): void {
        expect(Schema::hasIndex('conversations', 'conversations_ad_last_msg_idx'))->toBeTrue()
            ->and(Schema::hasIndex('offers', 'offers_ad_created_idx'))->toBeTrue()
            ->and(Schema::hasIndex('orders', 'orders_ad_status_idx'))->toBeTrue()
            ->and(Schema::hasIndex('ledger_transactions', 'ledger_transactions_reference_idx'))->toBeTrue();
    });
});

it('keeps the deal strings in step between Arabic and English', function (): void {
    $keys = fn (string $locale): array => array_keys(Arr::dot(trans('admin.deal', [], $locale)));

    expect($keys('en'))->toEqual($keys('ar'));
});
