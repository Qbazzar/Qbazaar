<?php

declare(strict_types=1);

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\Support\SupportTicketCreatedNotification;
use App\Notifications\Support\SupportTicketRepliedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

function supportTicketPayload(array $overrides = []): array
{
    return array_merge([
        'subject' => 'Cannot publish my ad',
        'category' => 'technical',
        'body' => 'The publish button keeps spinning forever.',
    ], $overrides);
}

function staffWithRole(string $role): User
{
    $staff = User::factory()->create();
    $staff->assignRole($role);

    return $staff;
}

it('attaches the ticket to the bearer token owner without asking for an email', function (): void {
    $user = User::factory()->create();

    $response = test()->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/v1/support/tickets', supportTicketPayload())
        ->assertCreated()
        ->assertJsonPath('data.email', null);

    $ticket = SupportTicket::query()->findOrFail($response->json('data.id'));
    expect($ticket->user_id)->toBe($user->id);
});

it('requires an email from guests and stores it on the ticket', function (): void {
    postJson('/api/v1/support/tickets', supportTicketPayload())
        ->assertUnprocessable()
        ->assertJsonPath('error.details.email.0', fn ($message) => is_string($message));

    postJson('/api/v1/support/tickets', supportTicketPayload(['email' => 'guest@example.com']))
        ->assertCreated()
        ->assertJsonPath('data.email', 'guest@example.com');

    expect(SupportTicket::query()->sole()->user_id)->toBeNull();
});

it('notifies support handlers about a new ticket', function (): void {
    $superAdmin = staffWithRole('super_admin');
    $supportAgent = staffWithRole('support');
    $moderator = staffWithRole('moderator');
    $customer = User::factory()->create();

    $ticketId = postJson('/api/v1/support/tickets', supportTicketPayload(['email' => 'guest@example.com']))
        ->assertCreated()
        ->json('data.id');

    Notification::assertSentTo(
        [$superAdmin, $supportAgent],
        SupportTicketCreatedNotification::class,
        function (SupportTicketCreatedNotification $notification, array $channels, User $notifiable) use ($ticketId): bool {
            $payload = $notification->toArray($notifiable);

            return $channels === ['database']
                && $payload['ticket_id'] === $ticketId
                && $payload['cta_url'] === route('admin.support.show', $ticketId);
        },
    );
    Notification::assertNotSentTo([$moderator, $customer], SupportTicketCreatedNotification::class);
});

it('notifies the ticket owner when staff reply and advances the ticket', function (): void {
    $owner = User::factory()->create();
    $ticket = SupportTicket::query()->create(supportTicketPayload(['user_id' => $owner->id]));
    $agent = staffWithRole('support');

    actingAs($agent)
        ->post("/admin/support/{$ticket->id}/reply", ['body' => 'We fixed the publish flow, please retry.'])
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::IN_PROGRESS)
        ->and($ticket->replies()->sole()->is_staff)->toBeTrue();

    Notification::assertSentTo(
        $owner,
        SupportTicketRepliedNotification::class,
        function (SupportTicketRepliedNotification $notification, array $channels) use ($owner, $ticket): bool {
            $payload = $notification->toArray($owner);

            return in_array('database', $channels, true)
                && $payload['category'] === 'support.reply'
                && $payload['ticket_id'] === $ticket->id;
        },
    );
});

it('emails the reply to a guest ticket contact address', function (): void {
    $ticket = SupportTicket::query()->create(supportTicketPayload(['email' => 'guest@example.com']));

    actingAs(staffWithRole('support'))
        ->post("/admin/support/{$ticket->id}/reply", ['body' => 'Thanks for reaching out, here is the fix.'])
        ->assertRedirect();

    Notification::assertSentOnDemand(
        SupportTicketRepliedNotification::class,
        fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
            && $notifiable->routeNotificationFor('mail') === 'guest@example.com',
    );
});

it('lists the owner\'s tickets with reply counts and pagination meta', function (): void {
    $owner = User::factory()->create();
    $ticket = SupportTicket::query()->create(supportTicketPayload(['user_id' => $owner->id]));
    SupportTicket::query()->create(supportTicketPayload(['user_id' => User::factory()->create()->id]));
    $ticket->replies()->create(['author_id' => $owner->id, 'is_staff' => false, 'body' => 'Any news?']);

    actingAs($owner, 'sanctum')
        ->getJson('/api/v1/account/support/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ticket->id)
        ->assertJsonPath('data.0.replies_count', 1)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 1);
});

it('shows the owner\'s ticket with its thread oldest first and hides others\' tickets', function (): void {
    $owner = User::factory()->create();
    $ticket = SupportTicket::query()->create(supportTicketPayload(['user_id' => $owner->id]));
    $first = $ticket->replies()->create(['author_id' => $owner->id, 'is_staff' => false, 'body' => 'First']);
    $first->forceFill(['created_at' => now()->subHour()])->save();
    $ticket->replies()->create(['author_id' => staffWithRole('support')->id, 'is_staff' => true, 'body' => 'Second']);

    actingAs($owner, 'sanctum')
        ->getJson("/api/v1/account/support/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.body', $ticket->body)
        ->assertJsonPath('data.replies_count', 2)
        ->assertJsonPath('data.replies.0.body', 'First')
        ->assertJsonPath('data.replies.1.author.is_staff', true);

    actingAs(User::factory()->create(), 'sanctum')
        ->getJson("/api/v1/account/support/tickets/{$ticket->id}")
        ->assertJsonPath('error.code', 'TICKET_002');
});

it('moves a ticket waiting on the user back to open when the owner replies', function (): void {
    $owner = User::factory()->create();
    $ticket = SupportTicket::query()->create(supportTicketPayload(['user_id' => $owner->id]));
    $ticket->forceFill(['status' => SupportTicketStatus::WAITING_USER])->save();

    actingAs($owner, 'sanctum')
        ->postJson("/api/v1/account/support/tickets/{$ticket->id}/reply", ['body' => 'Still broken'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Still broken')
        ->assertJsonPath('data.author.id', $owner->id)
        ->assertJsonPath('data.author.is_staff', false);

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::OPEN)
        ->and($ticket->last_replied_at)->not->toBeNull();
});

it('refuses an owner reply on a closed ticket', function (): void {
    $owner = User::factory()->create();
    $ticket = SupportTicket::query()->create(supportTicketPayload(['user_id' => $owner->id]));
    $ticket->forceFill(['status' => SupportTicketStatus::CLOSED])->save();

    actingAs($owner, 'sanctum')
        ->postJson("/api/v1/account/support/tickets/{$ticket->id}/reply", ['body' => 'Hello?'])
        ->assertJsonPath('error.code', 'TICKET_003');

    expect($ticket->replies()->count())->toBe(0);
});

it('lets support staff set any status by hand', function (): void {
    $ticket = SupportTicket::query()->create(supportTicketPayload(['email' => 'guest@example.com']));

    actingAs(staffWithRole('support'))
        ->post("/admin/support/{$ticket->id}/status", ['status' => SupportTicketStatus::RESOLVED->value])
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::RESOLVED);
});
