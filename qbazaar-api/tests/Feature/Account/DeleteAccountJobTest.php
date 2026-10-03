<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\DataExportStatus;
use App\Enums\LedgerReferenceType;
use App\Enums\OrderStatus;
use App\Enums\OtpPurpose;
use App\Enums\QueueName;
use App\Enums\UserStatus;
use App\Exceptions\ErrorCode;
use App\Jobs\DeleteAccountJob;
use App\Jobs\SweepDueAccountDeletionsJob;
use App\Models\Ad;
use App\Models\BusinessProfile;
use App\Models\Conversation;
use App\Models\DataExport;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OtpCode;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserAddress;
use App\Notifications\Account\AccountDeletionOnHoldNotification;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Messaging\ConversationInbox;
use App\Services\Users\FollowGraph;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->seedReferenceData();
});

function userPendingDeletionFor(int $days): User
{
    return User::factory()->create([
        'status' => UserStatus::PENDING_DELETION,
        'deletion_requested_at' => now()->subDays($days),
    ]);
}

function runDeleteJob(User $user): void
{
    app()->call([new DeleteAccountJob($user->id), 'handle']);
}

it('erases a due account with its ads, images, search documents, offers and chats', function (): void {
    $engine = Mockery::spy(Engine::class);
    app(EngineManager::class)->extend('spy', fn () => $engine);
    config()->set('scout.driver', 'spy');

    $user = userPendingDeletionFor(31);
    $other = User::factory()->create();

    $ad = $this->makeAd($user, ['status' => 'active']);
    $ad->addMedia(UploadedFile::fake()->image('photo.jpg', 50, 50))->toMediaCollection('images');
    $media = $ad->getFirstMedia('images');
    $mediaDisk = Storage::disk($media->disk);
    expect($mediaDisk->exists($media->getPathRelativeToRoot()))->toBeTrue();

    $otherAd = $this->makeAd($other, ['status' => 'active']);
    $ownAdChat = Conversation::factory()->create(['ad_id' => $ad->id, 'buyer_id' => $other->id, 'seller_id' => $user->id]);
    $buyingChat = Conversation::factory()->create(['ad_id' => $otherAd->id, 'buyer_id' => $user->id, 'seller_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $buyingChat->id, 'sender_id' => $user->id]);
    Offer::factory()->create(['conversation_id' => $ownAdChat->id, 'ad_id' => $ad->id, 'buyer_id' => $other->id, 'seller_id' => $user->id]);
    Offer::factory()->create(['conversation_id' => $buyingChat->id, 'ad_id' => $otherAd->id, 'buyer_id' => $user->id, 'seller_id' => $other->id]);
    UserAddress::factory()->for($user)->create();
    $user->createToken('device');
    $export = new DataExport;
    $export->forceFill(['user_id' => $user->id, 'status' => DataExportStatus::READY, 'path' => 'exports/old.json'])->save();
    Storage::disk('local')->put('exports/old.json', '{}');

    runDeleteJob($user);

    expect(User::withTrashed()->find($user->id))->toBeNull()
        ->and(Ad::withTrashed()->find($ad->id))->toBeNull()
        ->and($mediaDisk->exists($media->getPathRelativeToRoot()))->toBeFalse()
        ->and(Offer::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(UserAddress::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists('exports/old.json'))->toBeFalse()
        ->and(Ad::query()->whereKey($otherAd->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($other->id)->exists())->toBeTrue();

    $engine->shouldHaveReceived('delete')->withArgs(
        fn ($models): bool => $models->contains(fn ($model): bool => $model->getKey() === $ad->id),
    );
});

it('removes follows, favorites, codes, devices and the business profile, lowering the other side counters', function (): void {
    $user = userPendingDeletionFor(31);
    $seller = User::factory()->create();
    $fan = User::factory()->create();
    $graph = app(FollowGraph::class);
    $graph->link($user, $seller);
    $graph->link($fan, $user);

    $sellerAd = $this->makeAd($seller, ['status' => 'active']);
    Favorite::query()->create(['user_id' => $user->id, 'ad_id' => $sellerAd->id, 'created_at' => now()]);
    $sellerAd->forceFill(['favorites_count' => 1])->save();

    foreach ([$user->phone, $user->email] as $recipient) {
        OtpCode::query()->create([
            'recipient' => $recipient,
            'purpose' => OtpPurpose::PHONE_VERIFICATION,
            'code_hash' => 'x',
            'expires_at' => now()->addMinutes(5),
        ]);
    }
    TrustedDevice::query()->create(['user_id' => $user->id, 'device_hash' => str_repeat('a', 64), 'last_used_at' => now()]);
    $user->businessProfile()->create(['business_name' => 'Shop']);
    $hidden = Conversation::factory()->create(['ad_id' => $sellerAd->id, 'buyer_id' => $user->id, 'seller_id' => $seller->id]);
    app(ConversationInbox::class)->hide($user, [$hidden->id]);

    runDeleteJob($user);

    expect(Follow::query()->count())->toBe(0)
        ->and($seller->fresh()->followers_count)->toBe(0)
        ->and($fan->fresh()->following_count)->toBe(0)
        ->and(Favorite::query()->count())->toBe(0)
        ->and($sellerAd->fresh()->favorites_count)->toBe(0)
        ->and(OtpCode::query()->count())->toBe(0)
        ->and(TrustedDevice::query()->count())->toBe(0)
        ->and(BusinessProfile::query()->count())->toBe(0)
        ->and(Conversation::query()->whereKey($hidden->id)->exists())->toBeFalse();
});

it('leaves an account alone while its grace period is still running', function (): void {
    $user = userPendingDeletionFor(10);

    runDeleteJob($user);

    expect($user->fresh())->not->toBeNull();
});

it('leaves an account alone after the user signed back in', function (): void {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE, 'deletion_requested_at' => null]);

    runDeleteJob($user);

    expect($user->fresh())->not->toBeNull();
});

it('does nothing for an account that is already gone', function (): void {
    app()->call([new DeleteAccountJob('01HZZZZZZZZZZZZZZZZZZZZZZZ'), 'handle']);
})->throwsNoExceptions();

it('runs on the low queue without overlapping, and the lock expires before the first retry', function (): void {
    $job = new DeleteAccountJob('01HZZZZZZZZZZZZZZZZZZZZZZZ');
    $middleware = $job->middleware();

    expect($job->queue)->toBe('low')
        ->and($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->expiresAfter)->toBeGreaterThan($job->timeout)
        ->and($middleware[0]->expiresAfter)->toBeLessThan($job->backoff[0]);
});

it('sweeps only the accounts whose grace period is over', function (): void {
    Bus::fake([DeleteAccountJob::class]);

    $due = userPendingDeletionFor(31);
    userPendingDeletionFor(5);
    User::factory()->create();

    (new SweepDueAccountDeletionsJob)->handle();

    Bus::assertDispatchedTimes(DeleteAccountJob::class, 1);
    Bus::assertDispatched(DeleteAccountJob::class, fn (DeleteAccountJob $job): bool => $job->userId === $due->id);
});

it('schedules the sweep daily', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'accounts.sweep-deletions');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *');
});

function chargeErasureTestCommission(User $seller, string $amount): Order
{
    $order = Order::factory()->status(OrderStatus::COMPLETED)->create([
        'ad_id' => null,
        'seller_id' => $seller->id,
        'commission_amount' => $amount,
    ]);

    app(LedgerRecipes::class)->chargeCommission($order, LedgerActor::system());

    return $order;
}

it('keeps a due account pending and tells the user when commission became owed during the grace period', function (): void {
    Notification::fake();
    $user = userPendingDeletionFor(31);
    chargeErasureTestCommission($user, '7.25');

    runDeleteJob($user);
    runDeleteJob($user);

    expect($user->fresh())->not->toBeNull()
        ->and($user->fresh()->status)->toBe(UserStatus::PENDING_DELETION);
    Notification::assertSentToTimes($user, AccountDeletionOnHoldNotification::class, 1);
    Notification::assertSentTo($user, function (AccountDeletionOnHoldNotification $notification, array $channels) use ($user): bool {
        return $notification->details['amount'] === '7.25'
            && str_contains($notification->toArray($user)['body'], '7.25')
            && in_array('mail', $channels, true);
    });
});

it('erases the account once the debt that held it is settled', function (): void {
    Notification::fake();
    $user = userPendingDeletionFor(31);
    chargeErasureTestCommission($user, '7.25');
    runDeleteJob($user);

    app(LedgerRecipes::class)->settleCommissionByBankTransfer(
        $user->id,
        '7.25',
        new LedgerReference(LedgerReferenceType::SETTLEMENT, (string) Str::ulid()),
        LedgerActor::system(),
    );
    runDeleteJob($user);

    expect(User::withTrashed()->find($user->id))->toBeNull();
});

it('keeps a due account pending while it has an order in progress', function (): void {
    Notification::fake();
    $user = userPendingDeletionFor(31);
    $order = Order::factory()->status(OrderStatus::AWAITING_HANDOVER)->create(['ad_id' => null, 'buyer_id' => $user->id]);

    runDeleteJob($user);

    expect($user->fresh())->not->toBeNull()
        ->and($order->fresh()->buyer_id)->toBe($user->id);
    Notification::assertSentTo($user, AccountDeletionOnHoldNotification::class);
});

it('sends the on-hold notice on the notifications queue', function (): void {
    $notification = new AccountDeletionOnHoldNotification(ErrorCode::ACCOUNT_HAS_OPEN_ORDER);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and(array_unique(array_values($notification->viaQueues())))->toBe([QueueName::NOTIFICATIONS->value]);
});
