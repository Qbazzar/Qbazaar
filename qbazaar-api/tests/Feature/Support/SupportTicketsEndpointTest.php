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

    postJson('/api/v1/support/tickets', supportTicketPayload(['email' => 'guest@example.com']))
        ->assertCreated();

    Notification::assertSentTo([$superAdmin, $supportAgent], SupportTicketCreatedNotification::class);
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
