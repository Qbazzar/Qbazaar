<?php

declare(strict_types=1);

use App\Enums\DataExportStatus;
use App\Enums\UserStatus;
use App\Jobs\DeleteAccountJob;
use App\Jobs\SweepDueAccountDeletionsJob;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\DataExport;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Account\AccountEraser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
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
    (new DeleteAccountJob($user->id))->handle(app(AccountEraser::class));
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
    (new DeleteAccountJob('01HZZZZZZZZZZZZZZZZZZZZZZZ'))->handle(app(AccountEraser::class));
})->throwsNoExceptions();

it('runs on the low queue without overlapping, and the lock expires before the first retry', function (): void {
    $job = new DeleteAccountJob('01HZZZZZZZZZZZZZZZZZZZZZZZ');
    $middleware = $job->middleware();

    expect($job->queue)->toBe('low')
        ->and($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->expiresAfter)->toBeGreaterThan((int) config('horizon.defaults.supervisor-1.timeout'))
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
