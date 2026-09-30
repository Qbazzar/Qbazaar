<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Ads\AdSubmittedForReview;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDays(20),
    ]);
    Sanctum::actingAs($this->seller, ['*']);
});

it('sends an active ad back to review when its title changes', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    putJson("/api/v1/ads/{$this->ad->id}", ['title' => 'A completely different title'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    $ad = $this->ad->fresh();
    expect($ad->status)->toBe(AdStatus::PENDING)
        ->and($ad->title)->toBe('A completely different title')
        ->and($ad->published_at)->toBeNull();

    Event::assertDispatched(AdSubmittedForReview::class, fn (AdSubmittedForReview $event): bool => $event->ad->is($this->ad));
});

it('sends an active ad back to review when its description changes', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    putJson("/api/v1/ads/{$this->ad->id}", ['description' => str_repeat('Updated description text. ', 3)])
        ->assertOk();

    expect($this->ad->fresh()->status)->toBe(AdStatus::PENDING);
    Event::assertDispatchedTimes(AdSubmittedForReview::class, 1);
});

it('sends an active ad back to review when its category changes', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $otherCategory = Category::query()->whereKeyNot($this->ad->category_id)->value('id');

    putJson("/api/v1/ads/{$this->ad->id}", ['category_id' => $otherCategory])->assertOk();

    expect($this->ad->fresh()->status)->toBe(AdStatus::PENDING);
    Event::assertDispatched(AdSubmittedForReview::class);
});

it('removes the edited ad from the public feed until it is approved again', function (): void {
    putJson("/api/v1/ads/{$this->ad->id}", ['title' => 'A completely different title'])->assertOk();

    getJson('/api/v1/ads')->assertOk()->assertJsonCount(0, 'data');
});

it('notifies reviewers when a live ad is edited', function (): void {
    Role::findOrCreate('moderator', 'web');
    $reviewer = User::factory()->create();
    $reviewer->assignRole('moderator');

    putJson("/api/v1/ads/{$this->ad->id}", ['title' => 'A completely different title'])->assertOk();

    expect($reviewer->fresh()->notifications()->count())->toBe(1);
});

it('keeps an active ad live when only the price changes', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    putJson("/api/v1/ads/{$this->ad->id}", ['price' => 123])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::ACTIVE->value);

    expect($this->ad->fresh()->status)->toBe(AdStatus::ACTIVE);
    Event::assertNotDispatched(AdSubmittedForReview::class);
});

it('keeps an active ad live when the submitted title is unchanged', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    putJson("/api/v1/ads/{$this->ad->id}", ['title' => $this->ad->title])->assertOk();

    expect($this->ad->fresh()->status)->toBe(AdStatus::ACTIVE);
    Event::assertNotDispatched(AdSubmittedForReview::class);
});

it('does not re-submit a draft on edit', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $draft = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    putJson("/api/v1/ads/{$draft->id}", ['title' => 'A completely different title'])->assertOk();

    expect($draft->fresh()->status)->toBe(AdStatus::DRAFT);
    Event::assertNotDispatched(AdSubmittedForReview::class);
});

it('sends an active ad back to review when images are added', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Bus::fake();
    Event::fake([AdSubmittedForReview::class]);

    postJson("/api/v1/ads/{$this->ad->id}/images", [
        'images' => [UploadedFile::fake()->image('new.jpg', 800, 600)],
    ])->assertCreated();

    expect($this->ad->fresh()->status)->toBe(AdStatus::PENDING);
    Event::assertDispatched(AdSubmittedForReview::class);
});

it('does not re-submit a draft when images are added', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Bus::fake();
    Event::fake([AdSubmittedForReview::class]);

    $draft = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$draft->id}/images", [
        'images' => [UploadedFile::fake()->image('new.jpg', 800, 600)],
    ])->assertCreated();

    expect($draft->fresh()->status)->toBe(AdStatus::DRAFT);
    Event::assertNotDispatched(AdSubmittedForReview::class);
});

it('refuses new images on an expired ad, which renewing would publish unreviewed', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Bus::fake();

    $expired = $this->makeAd($this->seller, ['status' => AdStatus::EXPIRED->value]);

    postJson("/api/v1/ads/{$expired->id}/images", [
        'images' => [UploadedFile::fake()->image('new.jpg', 800, 600)],
    ])->assertForbidden();

    expect($expired->fresh()->getMedia('images'))->toBeEmpty();
});
