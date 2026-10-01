<?php

declare(strict_types=1);

use App\Enums\MessageType;
use App\Events\Messaging\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');

    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->phoneVerified()->create();
    $this->ad = $this->makeAd($this->seller);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
    $this->url = '/api/v1/conversations/' . $this->conversation->id . '/messages';
});

function sendImage(object $test, array $fields): TestResponse
{
    return post($test->url, ['type' => 'image', ...$fields], ['Accept' => 'application/json']);
}

it('stores a photo with a server-generated name and returns signed media links', function (): void {
    Event::fake([MessageSent::class]);
    Sanctum::actingAs($this->buyer, ['*']);

    $response = sendImage($this, [
        'image' => UploadedFile::fake()->image('../../evil.php.jpg', 800, 600),
        'body' => 'Is the scratch visible here?',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'image')
        ->assertJsonPath('data.body', 'Is the scratch visible here?');

    $message = Message::query()->sole();
    $media = $message->image();

    expect($media)->not->toBeNull()
        ->and($media->file_name)->toMatch('/^[0-9A-Z]{26}\.jpg$/')
        ->and($response->json('data.media.id'))->toBe($media->id)
        ->and($response->json('data.media.url'))->toContain('signature=')
        ->and($response->json('data.media.original_url'))->toContain('signature=');

    get($response->json('data.media.url'))->assertOk();
    get($response->json('data.media.original_url'))->assertOk();

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->is($message));
});

it('previews a photo without caption as "Photo" in the reader language', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    sendImage($this, ['image' => UploadedFile::fake()->image('photo.png', 300, 300)])
        ->assertCreated()
        ->assertJsonPath('data.body', '');

    expect($this->conversation->fresh()->last_message_preview)->toBe('Photo');

    Sanctum::actingAs($this->seller, ['*']);

    getJson('/api/v1/conversations?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.0.last_message_preview', 'صورة')
        ->assertJsonPath('data.0.last_message_key', 'image');

    getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.type', MessageType::IMAGE->value)
        ->assertJsonPath('data.0.media.mime_type', 'image/png');
});

it('includes the media in the broadcast payload', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    sendImage($this, ['image' => UploadedFile::fake()->image('photo.jpg', 200, 200)])->assertCreated();

    $message = Message::query()->sole();
    $payload = (new MessageSent($message->fresh(), $this->conversation->fresh(), $this->seller))->broadcastWith();

    expect($payload['message']['media']['id'])->toBe($message->image()?->id);
});

it('rejects images above the size limit and disallowed types', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    $tooLarge = (int) config('qbazaar.uploads.max_image_size_kb') + 1;

    sendImage($this, ['image' => UploadedFile::fake()->image('big.jpg')->size($tooLarge)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');

    sendImage($this, ['image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
        ->assertStatus(422);

    sendImage($this, [])->assertStatus(422);

    post($this->url, ['body' => 'hi', 'image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect(Message::query()->count())->toBe(0);
});

it('hides the photo from non-participants', function (): void {
    Sanctum::actingAs(User::factory()->phoneVerified()->create(), ['*']);

    sendImage($this, ['image' => UploadedFile::fake()->image('photo.jpg')])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'MSG_004');
});

it('refuses unsigned media links', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);
    sendImage($this, ['image' => UploadedFile::fake()->image('photo.jpg')])->assertCreated();

    $media = Message::query()->sole()->image();

    get('/api/v1/media/' . $media->id . '/conversions/preview')->assertForbidden();
    get('/api/v1/media/' . $media->id . '/original')->assertForbidden();
});
