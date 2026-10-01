<?php

declare(strict_types=1);

use App\Enums\DataExportStatus;
use App\Jobs\ExportUserDataJob;
use App\Models\Conversation;
use App\Models\DataExport;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\DataExportReadyNotification;
use App\Services\Account\UserDataExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

function readyExport(User $user, array $overrides = []): DataExport
{
    $export = new DataExport;
    $export->forceFill([
        'user_id' => $user->id,
        'status' => DataExportStatus::READY,
        'path' => 'exports/test.json',
        'expires_at' => now()->addHour(),
        ...$overrides,
    ])->save();

    Storage::disk('local')->put('exports/test.json', '{"ok":true}');

    return $export;
}

function downloadUrl(DataExport $export): string
{
    return URL::temporarySignedRoute('api.v1.account.data-export.download', now()->addHour(), ['id' => $export->id]);
}

it('records the request and queues the export job', function (): void {
    Bus::fake();
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/account/data-export-request')
        ->assertStatus(202)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.eta_minutes', 5);

    $export = DataExport::query()->sole();
    expect($export->user_id)->toBe($this->user->id)
        ->and($export->status)->toBe(DataExportStatus::QUEUED);

    Bus::assertDispatched(ExportUserDataJob::class, fn (ExportUserDataJob $job): bool => $job->exportId === $export->id);
});

it('throttles a second request inside 24h to 429 RATE_LIMIT_EXCEEDED', function (): void {
    Bus::fake();
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/account/data-export-request')->assertStatus(202);

    postJson('/api/v1/account/data-export-request')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');

    Bus::assertDispatchedTimes(ExportUserDataJob::class, 1);
    expect(DataExport::query()->count())->toBe(1);
});

it('rejects unauthenticated requests', function (): void {
    postJson('/api/v1/account/data-export-request')->assertStatus(401);
});

it('builds an export with ads, messages and offers, and emails a browser link', function (): void {
    Notification::fake();
    $this->seedReferenceData();

    $other = User::factory()->create();
    $ad = $this->makeAd($this->user, ['title' => 'My bike']);
    $otherAd = $this->makeAd($other);
    $chat = Conversation::factory()->create(['ad_id' => $otherAd->id, 'buyer_id' => $this->user->id, 'seller_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $chat->id, 'sender_id' => $this->user->id, 'body' => 'Is it available?']);
    Message::factory()->create(['conversation_id' => $chat->id, 'sender_id' => $other->id, 'body' => 'Not yours']);
    $made = Offer::factory()->create(['conversation_id' => $chat->id, 'ad_id' => $otherAd->id, 'buyer_id' => $this->user->id, 'seller_id' => $other->id]);

    $export = new DataExport;
    $export->forceFill(['user_id' => $this->user->id, 'status' => DataExportStatus::QUEUED])->save();

    (new ExportUserDataJob($export->id))->handle(app(UserDataExporter::class));

    $export->refresh();
    expect($export->status)->toBe(DataExportStatus::READY)
        ->and($export->expires_at)->not->toBeNull();

    $data = json_decode(Storage::disk('local')->get($export->path), true, flags: JSON_THROW_ON_ERROR);

    expect($data['user']['email'])->toBe($this->user->email)
        ->and($data['user'])->not->toHaveKey('password')
        ->and(array_column($data['ads'], 'title'))->toBe(['My bike'])
        ->and(array_column($data['messages_sent'], 'body'))->toBe(['Is it available?'])
        ->and(array_column($data['offers_made'], 'id'))->toBe([$made->id])
        ->and($data['offers_received'])->toBe([])
        ->and($data)->toHaveKeys(['addresses', 'favorites', 'saved_searches', 'reviews_written', 'blocked_users', 'sessions', 'activity_log']);

    Notification::assertSentTo(
        $this->user,
        DataExportReadyNotification::class,
        fn (DataExportReadyNotification $notification): bool => str_contains($notification->downloadUrl, "/account/data-export/{$export->id}?expires="),
    );
});

it('does not rebuild an export that is already ready', function (): void {
    Notification::fake();
    $export = readyExport($this->user);

    (new ExportUserDataJob($export->id))->handle(app(UserDataExporter::class));

    Notification::assertNothingSent();
});

it('downloads from the browser without a bearer token, once', function (): void {
    $export = readyExport($this->user);
    $url = downloadUrl($export);

    get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertDownload("qbazaar-data-export-{$export->id}.json");

    expect($export->fresh()->downloaded_at)->not->toBeNull();

    get($url, ['Accept' => 'application/json'])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'EXPORT_001');
});

it('refuses an expired export', function (): void {
    $export = readyExport($this->user, ['expires_at' => now()->subMinute()]);

    get(downloadUrl($export), ['Accept' => 'application/json'])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'EXPORT_001');
});

it('refuses a link with a tampered signature', function (): void {
    $export = readyExport($this->user);

    get(downloadUrl($export) . 'x', ['Accept' => 'application/json'])->assertForbidden();

    expect($export->fresh()->downloaded_at)->toBeNull();
});

it('prunes expired exports with their files', function (): void {
    $export = readyExport($this->user, ['expires_at' => now()->subDay()]);

    $this->artisan('model:prune', ['--model' => [DataExport::class]])->assertSuccessful();

    expect(DataExport::query()->find($export->id))->toBeNull()
        ->and(Storage::disk('local')->exists('exports/test.json'))->toBeFalse();
});
