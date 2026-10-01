<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\Language;
use App\Enums\MessageType;
use App\Events\Messaging\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create(['language' => Language::ARABIC]);
    $this->buyer = User::factory()->phoneVerified()->create(['language' => Language::ENGLISH]);
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
});

function makeOfferThroughApi(object $test, string $note = ''): Offer
{
    Sanctum::actingAs($test->buyer, ['*']);

    $payload = ['amount' => 150];
    if ($note !== '') {
        $payload['note'] = $note;
    }

    postJson('/api/v1/conversations/' . $test->conversation->id . '/offers', $payload)->assertCreated();

    return Offer::query()->latest('id')->firstOrFail();
}

it('stores offer bubbles as a key with params and an English fallback body', function (): void {
    makeOfferThroughApi($this, 'Cash today');

    $message = Message::query()->where('type', MessageType::OFFER->value)->firstOrFail();

    expect($message->message_key)->toBe('offer.made')
        ->and($message->params)->toBe(['amount' => '150.00', 'currency' => 'QAR', 'note' => 'Cash today'])
        ->and($message->body)->toBe('Offer: 150.00 QAR — Cash today');
});

it('renders system bubbles in the reader language', function (): void {
    $offer = makeOfferThroughApi($this);

    Sanctum::actingAs($this->seller, ['*']);
    postJson('/api/v1/offers/' . $offer->id . '/accept')->assertOk();

    $arabic = getJson('/api/v1/conversations/' . $this->conversation->id . '/messages?lang=ar')->assertOk();

    expect($arabic->json('data.0.message_key'))->toBe('offer.accepted')
        ->and($arabic->json('data.0.body'))->toBe('تم قبول العرض')
        ->and($arabic->json('data.1.body'))->toBe('عرض: 150.00 QAR')
        ->and($arabic->json('data.1.params'))->toBe(['amount' => '150.00', 'currency' => 'QAR']);

    $english = getJson('/api/v1/conversations/' . $this->conversation->id . '/messages?lang=en')->assertOk();

    expect($english->json('data.0.body'))->toBe('Offer accepted');
});

it('translates the inbox preview of a keyed bubble', function (): void {
    $offer = makeOfferThroughApi($this);

    Sanctum::actingAs($this->seller, ['*']);
    postJson('/api/v1/offers/' . $offer->id . '/reject')->assertOk();

    getJson('/api/v1/conversations?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.0.last_message_preview', 'تم رفض العرض')
        ->assertJsonPath('data.0.last_message_key', 'offer.rejected');
});

it('keeps user-written text untouched', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/messages?lang=ar', ['body' => 'Offer accepted'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Offer accepted')
        ->assertJsonPath('data.message_key', null);
});

it('broadcasts the bubble in the recipient language with its key and params', function (): void {
    makeOfferThroughApi($this);
    $message = Message::query()->where('type', MessageType::OFFER->value)->firstOrFail();

    $payload = (new MessageSent($message, $this->conversation->fresh(), $this->seller))->broadcastWith();

    expect($payload['message']['body'])->toBe('عرض: 150.00 QAR')
        ->and($payload['message']['message_key'])->toBe('offer.made')
        ->and($payload['message']['params'])->toBe(['amount' => '150.00', 'currency' => 'QAR'])
        ->and($payload['conversation']['last_message_preview'])->toBe('عرض: 150.00 QAR')
        ->and(app()->getLocale())->toBe(config('app.locale'));
});

it('backfills keys for messages written before bubbles were keyed', function (): void {
    $offer = Offer::factory()->pending()->create([
        'conversation_id' => $this->conversation->id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'amount' => 99.5,
        'currency' => 'QAR',
        'note' => null,
    ]);

    $offerBubble = Message::query()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->buyer->id,
        'body' => 'Offer: 99.50 QAR',
        'type' => MessageType::OFFER,
    ]);
    $offer->forceFill(['message_id' => $offerBubble->id])->save();

    $orphanBubble = Message::query()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->buyer->id,
        'body' => 'Offer: 80.00 QAR — last price',
        'type' => MessageType::OFFER,
    ]);

    $systemBubble = Message::query()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->seller->id,
        'body' => 'Offer withdrawn',
        'type' => MessageType::SYSTEM,
    ]);

    $this->conversation->forceFill(['last_message_preview' => 'Offer withdrawn', 'last_message_at' => now()])->save();

    $migration = require database_path('migrations/2026_10_01_200100_backfill_chat_message_keys.php');
    DB::transaction(fn () => $migration->up());

    expect($offerBubble->fresh()->message_key)->toBe('offer.made')
        ->and($offerBubble->fresh()->params)->toBe(['amount' => '99.50', 'currency' => 'QAR'])
        ->and($orphanBubble->fresh()->params)->toBe(['amount' => '80.00', 'currency' => 'QAR', 'note' => 'last price'])
        ->and($systemBubble->fresh()->message_key)->toBe('offer.withdrawn')
        ->and($this->conversation->fresh()->last_message_key)->toBe('offer.withdrawn');
});
