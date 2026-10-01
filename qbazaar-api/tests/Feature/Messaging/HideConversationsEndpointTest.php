<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->phoneVerified()->create();

    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->makeAd($this->seller)->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'last_message_at' => now(),
    ]);
    $this->kept = Conversation::query()->create([
        'ad_id' => $this->makeAd($this->seller)->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'last_message_at' => now()->subHour(),
    ]);
});

function inboxIds(): array
{
    return array_column(getJson('/api/v1/conversations')->assertOk()->json('data'), 'id');
}

it('hides conversations for the requester only', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    deleteJson('/api/v1/conversations', ['ids' => [$this->conversation->id]])
        ->assertOk()
        ->assertJsonPath('data.hidden', 1);

    expect(inboxIds())->toBe([$this->kept->id]);

    Sanctum::actingAs($this->seller, ['*']);

    expect(inboxIds())->toBe([$this->conversation->id, $this->kept->id]);
});

it('brings a hidden conversation back on a new message', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);
    deleteJson('/api/v1/conversations', ['ids' => [$this->conversation->id]])->assertOk();

    Sanctum::actingAs($this->seller, ['*']);
    postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', ['body' => 'Still interested?'])
        ->assertCreated();

    Sanctum::actingAs($this->buyer, ['*']);

    expect(inboxIds())->toContain($this->conversation->id);
    getJson('/api/v1/conversations/unread-count')->assertJsonPath('data.total', 1);
});

it('leaves hidden conversations out of the unread badge', function (): void {
    Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->seller->id,
    ]);

    Sanctum::actingAs($this->buyer, ['*']);
    getJson('/api/v1/conversations/unread-count')->assertJsonPath('data.total', 1);

    deleteJson('/api/v1/conversations', ['ids' => [$this->conversation->id]])->assertOk();

    getJson('/api/v1/conversations/unread-count')->assertJsonPath('data.total', 0);
});

it('ignores ids of conversations the caller is not part of', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    deleteJson('/api/v1/conversations', ['ids' => [$this->conversation->id]])
        ->assertOk()
        ->assertJsonPath('data.hidden', 0);

    expect($this->conversation->fresh()->buyer_hidden_at)->toBeNull()
        ->and($this->conversation->fresh()->seller_hidden_at)->toBeNull();
});

it('validates the id list', function (array $payload): void {
    Sanctum::actingAs($this->buyer, ['*']);

    deleteJson('/api/v1/conversations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
})->with([
    'missing' => [fn (): array => []],
    'empty' => [fn (): array => ['ids' => []]],
    'not ulids' => [fn (): array => ['ids' => ['nope']]],
    'too many' => [fn (): array => ['ids' => array_map(fn (): string => (string) Str::ulid(), range(1, 101))]],
]);

it('requires authentication', function (): void {
    deleteJson('/api/v1/conversations', ['ids' => [$this->conversation->id]])->assertUnauthorized();
});
