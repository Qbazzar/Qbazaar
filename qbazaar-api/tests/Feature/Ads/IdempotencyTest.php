<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->phoneVerified()->create();
    Sanctum::actingAs($this->user, ['*']);
    Cache::flush();
});

it('replays the cached response on duplicate X-Idempotency-Key', function (): void {
    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Pristine bookshelf for a cosy reading nook',
        'description' => 'Solid oak bookshelf, light wear on the corners. Pick up from Lusail any evening this week.',
    ]);

    $key = 'idem_test_' . str_repeat('a', 24);

    $first = postJson("/api/v1/ads/{$ad->id}/publish", [], [
        'Accept' => 'application/json',
        'X-Idempotency-Key' => $key,
    ])->assertOk();

    $firstStatus = $first->json('data.status');

    // Mutate the ad behind the scenes so we can prove the second call did
    // NOT re-run the controller (otherwise it would see the new state).
    $ad->forceFill(['status' => AdStatus::DRAFT->value])->save();

    $second = postJson("/api/v1/ads/{$ad->id}/publish", [], [
        'Accept' => 'application/json',
        'X-Idempotency-Key' => $key,
    ])->assertOk();

    expect($second->json('data.status'))->toBe($firstStatus)
        ->and($second->headers->get('X-Idempotent-Replay'))->toBe('true');
});

it('processes a fresh idempotency key normally', function (): void {
    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Pristine bookshelf for a cosy reading nook',
        'description' => 'Solid oak bookshelf, light wear on the corners. Pick up from Lusail any evening this week.',
    ]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], [
        'Accept' => 'application/json',
        'X-Idempotency-Key' => 'idem_test_' . str_repeat('b', 24),
    ])
        ->assertOk()
        ->assertHeaderMissing('X-Idempotent-Replay');
});
