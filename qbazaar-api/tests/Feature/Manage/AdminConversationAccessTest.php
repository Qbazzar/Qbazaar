<?php

declare(strict_types=1);

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\ConversationAccessLog;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\OpensConversations;

uses(RefreshDatabase::class, CreatesAds::class, CountsPageQueries::class, OpensConversations::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->conversation = Conversation::factory()->create();
    Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->conversation->buyer_id,
        'type' => MessageType::TEXT,
        'body' => 'secret-transcript-line',
    ]);
});

function accessStaff(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

it('asks every role that may read conversations for a reason before the transcript', function (string $role): void {
    actingAs(accessStaff($role));

    $this->get(route('admin.conversations.show', $this->conversation))
        ->assertOk()
        ->assertSee(__('admin.conversation_access.form_title'))
        ->assertSee(route('admin.conversations.access', $this->conversation), false)
        ->assertDontSee('secret-transcript-line');

    expect(ConversationAccessLog::query()->count())->toBe(0);
})->with(['super_admin', 'moderator']);

it('refuses staff without conversations.view on the page and on the reason form', function (): void {
    actingAs(accessStaff('support'));

    $this->get(route('admin.conversations.show', $this->conversation))->assertForbidden();
    $this->post(route('admin.conversations.access', $this->conversation), ['reason' => 'Looking into a complaint'])->assertForbidden();

    expect(ConversationAccessLog::query()->count())->toBe(0);
});

it('records who opened the conversation, when and why, then shows the transcript', function (string $role): void {
    $staff = accessStaff($role);
    actingAs($staff);
    $this->travelTo(now()->startOfMinute());

    $this->post(route('admin.conversations.access', $this->conversation), ['reason' => '  Report #42: checking the seller\'s replies  '])
        ->assertRedirect(route('admin.conversations.show', $this->conversation))
        ->assertSessionHas('status', __('admin.conversation_access.flash.granted'));

    $log = ConversationAccessLog::query()->sole();
    expect($log->staff_id)->toBe($staff->id)
        ->and($log->conversation_id)->toBe($this->conversation->id)
        ->and($log->reason)->toBe('Report #42: checking the seller\'s replies')
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and($log->created_at->equalTo(now()))->toBeTrue();

    $audit = Activity::query()->where('event', 'admin.conversations.opened')->sole();
    expect($audit->causer_id)->toBe($staff->id)
        ->and($audit->subject_id)->toBe($this->conversation->id)
        ->and($audit->properties['reason'])->toBe('Report #42: checking the seller\'s replies');

    $this->get(route('admin.conversations.show', $this->conversation))
        ->assertOk()
        ->assertSee('secret-transcript-line')
        ->assertSee(__('admin.conversation_access.log_title'))
        ->assertSee('Report #42: checking the seller&#039;s replies', false);
})->with(['super_admin', 'moderator']);

it('rejects a missing, short, long or marked-up reason without logging anything', function (mixed $reason): void {
    actingAs(accessStaff('moderator'));

    $this->from(route('admin.conversations.show', $this->conversation))
        ->post(route('admin.conversations.access', $this->conversation), ['reason' => $reason])
        ->assertSessionHasErrors('reason');

    expect(ConversationAccessLog::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'admin.conversations.opened')->count())->toBe(0);

    $this->get(route('admin.conversations.show', $this->conversation))->assertDontSee('secret-transcript-line');
})->with([
    'missing' => [null],
    'too short' => ['because'],
    'too long' => [str_repeat('a', 501)],
    'markup' => ['<script>alert(1)</script> checking'],
]);

it('returns to the transcript page the staff member asked for', function (): void {
    actingAs(accessStaff('moderator'));

    $this->get(route('admin.conversations.show', ['conversation' => $this->conversation, 'page' => 2]))
        ->assertSee('name="page" value="2"', false);

    $this->post(route('admin.conversations.access', $this->conversation), ['reason' => 'Reading the older messages', 'page' => 2])
        ->assertRedirect(route('admin.conversations.show', ['conversation' => $this->conversation, 'page' => 2]));
});

it('asks again once the grant expires', function (): void {
    actingAs(accessStaff('moderator'));
    $this->openConversation($this->conversation);

    $this->travel((int) config('qbazaar.admin.conversation_access.grant_minutes') - 1)->minutes();
    $this->get(route('admin.conversations.show', $this->conversation))->assertSee('secret-transcript-line');

    $this->travel(2)->minutes();
    $this->get(route('admin.conversations.show', $this->conversation))
        ->assertDontSee('secret-transcript-line')
        ->assertSee(__('admin.conversation_access.form_title'));
});

it('grants one conversation at a time and never to another staff member in the session', function (): void {
    actingAs(accessStaff('moderator'));
    $this->openConversation($this->conversation);
    $other = Conversation::factory()->create();

    $this->get(route('admin.conversations.show', $other))->assertSee(__('admin.conversation_access.form_title'));

    actingAs(accessStaff('super_admin'));
    $this->get(route('admin.conversations.show', $this->conversation))
        ->assertDontSee('secret-transcript-line')
        ->assertSee(__('admin.conversation_access.form_title'));
});

it('keeps the transcript query count flat as the access log grows', function (): void {
    $staff = accessStaff('super_admin');
    actingAs($staff);
    $this->openConversation($this->conversation);
    $uri = route('admin.conversations.show', $this->conversation);
    $few = $this->steadyQueryCount($uri);

    foreach (range(1, 12) as $i) {
        actingAs(accessStaff('moderator'));
        $this->openConversation($this->conversation, "Follow-up review number {$i}");
    }
    actingAs($staff);

    expect($this->steadyQueryCount($uri))->toBe($few)
        ->and(ConversationAccessLog::query()->count())->toBe(13);

    $this->get($uri)->assertSee(__('admin.conversation_access.log_count', ['count' => 13]));
});

it('keeps people\'s own words out of the ad deal panel but still names platform events', function (): void {
    actingAs(accessStaff('super_admin'));
    $this->conversation->forceFill([
        'last_message_preview' => 'secret-transcript-line',
        'last_message_key' => null,
        'last_message_at' => now(),
    ])->save();

    $this->get(route('admin.ads.show', $this->conversation->ad_id))
        ->assertOk()
        ->assertSee('id="ad-conversations-title"', false)
        ->assertSee(__('admin.deal.conversations.private_message'))
        ->assertDontSee('secret-transcript-line');

    $this->conversation->forceFill([
        'last_message_preview' => 'Offer: 200 QAR',
        'last_message_key' => 'offer.made',
        'last_message_params' => ['amount' => '200', 'currency' => 'QAR', 'note' => 'secret-offer-note'],
    ])->save();

    $this->get(route('admin.ads.show', $this->conversation->ad_id))
        ->assertOk()
        ->assertSee(__('chat.offer.made', ['amount' => '200', 'currency' => 'QAR']))
        ->assertDontSee('secret-offer-note')
        ->assertDontSee(__('admin.deal.conversations.private_message'));
});
