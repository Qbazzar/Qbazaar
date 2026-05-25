<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Events\Reports\ReportCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->reporter = User::factory()->create();
    $this->target = User::factory()->create();
    $this->ad = $this->makeAd($this->target, ['status' => AdStatus::ACTIVE->value]);
});

it('creates a pending report against an ad', function (): void {
    Event::fake([ReportCreated::class]);

    Sanctum::actingAs($this->reporter, ['*']);

    $response = postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::SPAM->value,
        'description' => 'Looks like a fake listing.',
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->where('data.target_type', 'ad')
            ->where('data.target_id', $this->ad->id)
            ->where('data.category', 'spam')
            ->where('data.status', ReportStatus::PENDING->value)
            ->etc());

    expect(Report::query()->count())->toBe(1);
    Event::assertDispatched(ReportCreated::class);
});

it('creates a report against a user', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::USER->value,
        'target_id' => $this->target->id,
        'category' => ReportCategory::OFFENSIVE->value,
    ])->assertStatus(201);
});

it('creates a report against a conversation', function (): void {
    $conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->reporter->id,
        'seller_id' => $this->target->id,
    ]);

    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::CONVERSATION->value,
        'target_id' => $conversation->id,
        'category' => ReportCategory::INAPPROPRIATE->value,
    ])->assertStatus(201);
});

it('creates a report against a message', function (): void {
    $conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->reporter->id,
        'seller_id' => $this->target->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->target->id,
    ]);

    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::MESSAGE->value,
        'target_id' => $message->id,
        'category' => ReportCategory::OFFENSIVE->value,
    ])->assertStatus(201);
});

it('refuses self-report on user target', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::USER->value,
        'target_id' => $this->reporter->id,
        'category' => ReportCategory::OFFENSIVE->value,
    ])->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'REPORT_001')->etc());
});

it('refuses when target id does not exist', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => strtolower((string) Str::ulid()),
        'category' => ReportCategory::SPAM->value,
    ])->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'REPORT_003')->etc());
});

it('requires authentication', function (): void {
    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::SPAM->value,
    ])->assertStatus(401);
});

it('validates target_type enum membership', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => 'review', // not in enum
        'target_id' => $this->ad->id,
        'category' => ReportCategory::SPAM->value,
    ])->assertStatus(422);
});

it('validates category enum membership', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => 'not-a-real-category',
    ])->assertStatus(422);
});

it('validates description max length', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::OTHER->value,
        'description' => str_repeat('a', 1001),
    ])->assertStatus(422);
});
