<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\AdModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    config(['scout.driver' => 'collection']);

    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

it('queues search syncing and defers it until the transaction commits', function (): void {
    expect(config('scout.queue'))->toBeTrue()
        ->and(config('scout.after_commit'))->toBeTrue();
});

it('queues exactly one index job when an ad goes live', function (): void {
    $ad = $this->makeAd($this->user, ['status' => AdStatus::PENDING->value]);

    Queue::fake([MakeSearchable::class, RemoveFromSearch::class]);

    app(AdModerationService::class)->approve($ad);

    Queue::assertPushed(MakeSearchable::class, 1);
    Queue::assertNotPushed(RemoveFromSearch::class);
});

it('queues exactly one removal job when an ad is sold', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->user->id]);

    Queue::fake([MakeSearchable::class, RemoveFromSearch::class]);

    postJson("/api/v1/ads/{$ad->id}/mark-sold", [], ['Accept' => 'application/json'])
        ->assertOk();

    Queue::assertPushed(RemoveFromSearch::class, 1);
    Queue::assertNotPushed(MakeSearchable::class);
});
