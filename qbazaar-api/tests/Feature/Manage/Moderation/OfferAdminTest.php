<?php

declare(strict_types=1);

use App\Enums\DisputeResolution;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\Offers\OfferExpired;
use App\Models\Offer;
use App\Models\User;
use App\Services\Moderation\OfferCancellation;
use App\Services\Orders\OrderTransitionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\PlacesOrders;

uses(RefreshDatabase::class, CreatesAds::class, PlacesOrders::class, CountsPageQueries::class);

/*
 * AD-17.4: handle an offer from the panel. Staff cancel an open offer with a
 * reason, and rule on a dispute through the finance dispute flow (no money
 * logic of its own). Both land in the activity log.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null, 'qbazaar.commission.rate' => '5.00']);
    app()->setLocale('ar');
    Notification::fake();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->seller = User::factory()->create(['full_name' => 'Seller Sam']);
    $this->buyer = User::factory()->create(['full_name' => 'Buyer Bea']);
    $this->offer = $this->pendingOffer($this->seller, $this->buyer, amount: '350.00');
});

function offerStaff(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

it('shows the offer with its parties to staff who can view offers', function (string $role, bool $canCancel): void {
    actingAs(offerStaff($role));

    $html = $this->get(route('admin.offers.show', $this->offer))
        ->assertOk()
        ->assertSee('data-qb-page', false)
        ->assertSee('350.00')
        ->assertSee('Seller Sam')
        ->assertSee('Buyer Bea')
        ->getContent();

    expect(str_contains($html, route('admin.offers.cancel', $this->offer)))->toBe($canCancel);
})->with([
    'super_admin' => ['super_admin', true],
    'moderator' => ['moderator', true],
]);

it('is closed to staff without the offer permissions', function (): void {
    actingAs(offerStaff('support'));

    $this->get(route('admin.offers.show', $this->offer))->assertForbidden();
    $this->post(route('admin.offers.cancel', $this->offer), ['reason' => 'fraud'])->assertForbidden();

    expect($this->offer->fresh()->status)->toBe(OfferStatus::PENDING);
});

it('cancels an open offer with a reason, logs it and tells both sides', function (string $role): void {
    Event::fake([OfferExpired::class]);
    $staff = offerStaff($role);
    actingAs($staff);

    $this->post(route('admin.offers.cancel', $this->offer), ['reason' => 'fraud', 'reason_note' => 'Buyer flagged for fake transfers.'])
        ->assertRedirect(route('admin.offers.show', $this->offer))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('admin.offer_admin.flash.cancelled'));

    $entry = Activity::query()->where('event', OfferCancellation::AUDIT_EVENT)->sole();

    expect($this->offer->fresh()->status)->toBe(OfferStatus::EXPIRED)
        ->and($entry->causer_id)->toBe($staff->id)
        ->and($entry->subject_id)->toBe($this->offer->id)
        ->and($entry->properties['reason'])->toBe('fraud')
        ->and($entry->properties['note'])->toBe('Buyer flagged for fake transfers.');

    Event::assertDispatched(OfferExpired::class, fn (OfferExpired $event): bool => $event->offer->is($this->offer));

    // The offer's history on its page shows the cancellation.
    $this->get(route('admin.offers.show', $this->offer))
        ->assertOk()
        ->assertSee(__('admin.timeline.events.offers.cancel'));
})->with(['super_admin', 'moderator']);

it('needs a reason to cancel an offer', function (array $payload, string $field): void {
    actingAs(offerStaff('moderator'));

    $this->post(route('admin.offers.cancel', $this->offer), $payload)->assertSessionHasErrors($field);

    expect($this->offer->fresh()->status)->toBe(OfferStatus::PENDING);
})->with([
    'no reason' => [[], 'reason'],
    '"other" without a note' => [['reason' => 'other'], 'reason_note'],
]);

it('refuses to cancel an offer that is no longer open', function (): void {
    $order = $this->acceptOffer($this->offer);
    actingAs(offerStaff('super_admin'));

    $this->from(route('admin.offers.show', $this->offer))
        ->post(route('admin.offers.cancel', $this->offer), ['reason' => 'spam'])
        ->assertRedirect(route('admin.offers.show', $this->offer))
        ->assertSessionHas('error');

    expect($this->offer->fresh()->status)->toBe(OfferStatus::ACCEPTED)
        ->and($order->fresh()->status)->toBe(OrderStatus::CREATED)
        ->and(Activity::query()->where('event', OfferCancellation::AUDIT_EVENT)->count())->toBe(0);
});

it('rules on the order dispute from the offer page through the finance flow', function (): void {
    $order = $this->acceptOffer($this->offer);
    $transitions = app(OrderTransitionService::class);
    $transitions->awaitHandover($order, PaymentMethod::CASH);
    $transitions->dispute($order, $this->buyer->id, 'The item never arrived.');
    $show = route('admin.offers.show', $this->offer);

    actingAs(offerStaff('moderator'));
    $this->get($show)
        ->assertOk()
        ->assertSee('The item never arrived.')
        ->assertDontSee(route('admin.finance.disputes.resolve', $order), false);

    actingAs(offerStaff('super_admin'));
    $this->get($show)->assertOk()->assertSee(route('admin.finance.disputes.resolve', $order), false);

    $this->from($show)
        ->post(route('admin.finance.disputes.resolve', $order), ['resolution' => DisputeResolution::CANCELLED->value, 'note' => 'Seller could not show a handover.'])
        ->assertRedirect($show)
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->fresh()->dispute_resolution)->toBe(DisputeResolution::CANCELLED);
});

it('links each offer on the offers list to its page', function (): void {
    actingAs(offerStaff('moderator'));

    $this->get(route('admin.offers.index'))
        ->assertOk()
        ->assertSee(route('admin.offers.show', $this->offer), false);
});

it('keeps a fixed query budget however long the offer history is', function (): void {
    $staff = offerStaff('super_admin');
    actingAs($staff);
    $uri = route('admin.offers.show', $this->offer);

    activity('admin')->performedOn($this->offer)->causedBy($staff)->event('admin.offers.view')->log('admin.offers.view');
    $short = $this->steadyQueryCount($uri);

    foreach (range(1, 12) as $i) {
        activity('admin')->performedOn($this->offer)->causedBy(offerStaff('moderator'))->event('admin.offers.view')->log('admin.offers.view');
    }
    $long = $this->steadyQueryCount($uri);

    expect($long)->toBe($short)->and($short)->toBeLessThanOrEqual(14);
});
