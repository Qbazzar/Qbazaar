<?php

declare(strict_types=1);

use App\Actions\Reports\ReportFlaggedChatMessageAction;
use App\Enums\AdStatus;
use App\Enums\MessageType;
use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Events\Messaging\MessageSent;
use App\Events\Reports\ReportCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->phoneVerified()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
});

function postChatMessage(User $sender, Conversation $conversation, string $body): Message
{
    Sanctum::actingAs($sender, ['*']);

    $response = postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => $body])
        ->assertCreated()
        ->assertJsonPath('data.body', $body);

    return Message::query()->findOrFail($response->json('data.id'));
}

it('delivers a message with a banned word and reports it for review', function (): void {
    Event::fake([ReportCreated::class]);

    $message = postChatMessage($this->buyer, $this->conversation, 'Pay me in Bitcoin and I ship today');

    $report = Report::query()->sole();

    expect($report)
        ->reporter_id->toBeNull()
        ->target_type->toBe(ReportTarget::MESSAGE)
        ->target_id->toBe($message->id)
        ->category->toBe(ReportCategory::FRAUD)
        ->status->toBe(ReportStatus::PENDING)
        ->description->toContain('bitcoin');
    Event::assertDispatched(ReportCreated::class, fn (ReportCreated $event): bool => $event->report->is($report));
});

it('reports a message linking off the platform as spam', function (): void {
    $message = postChatMessage($this->buyer, $this->conversation, 'Better price here https://cheap-deals.example/bike');

    expect(Report::query()->sole())
        ->target_id->toBe($message->id)
        ->category->toBe(ReportCategory::SPAM)
        ->description->toContain('https://cheap-deals.example/bike');
});

it('does not report ordinary negotiation, including a phone number', function (): void {
    postChatMessage($this->buyer, $this->conversation, 'Is 900 fine? Call me on +974 5512 3456 after six');

    expect(Report::query()->count())->toBe(0);
});

it('does not screen offer and system bubbles', function (): void {
    $message = Message::query()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->buyer->id,
        'body' => 'bitcoin',
        'type' => MessageType::SYSTEM->value,
    ]);

    event(new MessageSent($message, $this->conversation, $this->seller));

    expect(Report::query()->count())->toBe(0);
});

it('stops reporting when chat auto-reports are switched off', function (string $configKey): void {
    config()->set($configKey, false);

    postChatMessage($this->buyer, $this->conversation, 'Pay me in Bitcoin');

    expect(Report::query()->count())->toBe(0);
})->with([
    'moderation kill switch' => 'moderation.enabled',
    'chat auto-report setting' => 'qbazaar.messaging.auto_report_flagged_messages',
]);

it('files one report per message when screening runs again', function (): void {
    $message = postChatMessage($this->buyer, $this->conversation, 'Pay me in Bitcoin');

    expect(app(ReportFlaggedChatMessageAction::class)->execute($message))->toBeNull()
        ->and(Report::query()->count())->toBe(1);
});

it('shows automatic reports in the admin panel', function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);

    postChatMessage($this->buyer, $this->conversation, 'Pay me in Bitcoin');
    $report = Report::query()->sole();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    $this->get('/admin/reports')->assertOk()->assertSee('الإشراف التلقائي');
    $this->get("/admin/reports/{$report->id}")->assertOk()->assertSee('الإشراف التلقائي');
});
