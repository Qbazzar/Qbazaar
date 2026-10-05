<?php

declare(strict_types=1);

use App\Enums\Language;
use App\Enums\QueueName;
use App\Events\PurchaseRequests\PurchaseRequestAccepted;
use App\Events\PurchaseRequests\PurchaseRequestCancelled;
use App\Events\PurchaseRequests\PurchaseRequestCreated;
use App\Events\PurchaseRequests\PurchaseRequestRejected;
use App\Events\PurchaseRequests\PurchaseRequestUpdated;
use App\Models\PurchaseRequest;
use App\Notifications\PurchaseRequests\PurchaseRequestInboxNotification;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CategorySeeder::class, LocationSeeder::class]);
    $this->request = PurchaseRequest::factory()->create();
    $this->buyer = $this->request->buyer;
    $this->seller = $this->request->seller;
});

it('writes an inbox row for the other side of every purchase request event', function (string $event, string $category, string $actor): void {
    $actor = $this->{$actor};
    $other = $actor->is($this->buyer) ? $this->seller : $this->buyer;

    $event::dispatch($this->request, $other->id);

    expect($other->notifications()->count())->toBe(1)
        ->and($actor->notifications()->count())->toBe(0)
        ->and($other->notifications()->first()->data['category'])->toBe($category)
        ->and($other->notifications()->first()->data['purchase_request_id'])->toBe($this->request->id);
})->with([
    'created' => [PurchaseRequestCreated::class, 'purchase_request.created', 'buyer'],
    'updated' => [PurchaseRequestUpdated::class, 'purchase_request.updated', 'buyer'],
    'cancelled by the buyer' => [PurchaseRequestCancelled::class, 'purchase_request.cancelled', 'buyer'],
    'accepted' => [PurchaseRequestAccepted::class, 'purchase_request.accepted', 'seller'],
    'rejected' => [PurchaseRequestRejected::class, 'purchase_request.rejected', 'seller'],
]);

it('tells the buyer when the platform cancelled the request', function (): void {
    PurchaseRequestCancelled::dispatch($this->request, $this->request->buyer_id);

    expect($this->buyer->notifications()->count())->toBe(1)
        ->and($this->seller->notifications()->count())->toBe(0);
});

it('sends no email', function (): void {
    Mail::fake();

    PurchaseRequestCreated::dispatch($this->request, $this->seller->id);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    expect($this->seller->notifications()->count())->toBe(1);
});

it('writes the text in the language of the recipient', function (): void {
    $this->seller->forceFill(['language' => Language::ENGLISH])->save();
    $this->buyer->forceFill(['language' => Language::ARABIC])->save();

    PurchaseRequestUpdated::dispatch($this->request, $this->seller->id);
    PurchaseRequestUpdated::dispatch($this->request, $this->buyer->id);

    expect($this->seller->notifications()->first()->data['title'])->toBe('Buy request updated')
        ->and($this->buyer->notifications()->first()->data['title'])->not->toBe('Buy request updated');
});

it('delivers the row from the notifications queue on the database channel only', function (): void {
    $notification = new PurchaseRequestInboxNotification($this->request, 'purchase_request.created');

    expect($notification->via($this->seller))->toBe(['database'])
        ->and($notification->viaQueues()['database'])->toBe(QueueName::NOTIFICATIONS->value);

    Notification::fake();
    PurchaseRequestCreated::dispatch($this->request, $this->seller->id);

    Notification::assertSentTo($this->seller, PurchaseRequestInboxNotification::class);
    Notification::assertNotSentTo($this->buyer, PurchaseRequestInboxNotification::class);
});
