<?php

declare(strict_types=1);

use App\Enums\ReportTarget;
use App\Enums\SupportTicketStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class, CountsPageQueries::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->requester = User::factory()->create(['full_name' => 'منى المشتكية', 'phone' => '+97455123456']);
});

function supportTicketFor(?User $requester, array $overrides = []): SupportTicket
{
    return SupportTicket::query()->create([
        'user_id' => $requester?->id,
        'subject' => 'لا أستطيع نشر إعلاني',
        'category' => 'technical',
        'body' => 'زر النشر لا يستجيب.',
        ...$overrides,
    ]);
}

/** Replies from distinct authors (some with photos) plus audit rows, so any per-row lookup would show. */
function giveTicketHistory(SupportTicket $ticket, User $staff, int $count): void
{
    foreach (range(1, $count) as $i) {
        $author = User::factory()->create();
        if ($i % 5 === 1) {
            $author->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('avatar');
        }
        SupportReply::query()->create([
            'ticket_id' => $ticket->id,
            'author_id' => $author->id,
            'is_staff' => $i % 2 === 0,
            'body' => "رد رقم {$i}",
        ]);

        supportTicketFor($ticket->user, ['subject' => "تذكرة سابقة {$i}"]);
        activity('admin')->performedOn($ticket)->causedBy($staff)->event('admin.support.reply')->log('admin.support.reply');
    }
}

describe('support.show', function (): void {
    it('renders in the shell for every role that can open tickets, with one h1 and the thread', function (string $role): void {
        $ticket = supportTicketFor($this->requester);
        actingAs(User::factory()->create()->assignRole($role));

        $html = $this->get(route('admin.support.show', $ticket))
            ->assertOk()
            ->assertSee('data-qb-page', false)
            ->assertSee('max-w-[96rem]', false)
            ->assertSee('لا أستطيع نشر إعلاني')
            ->assertSee('زر النشر لا يستجيب.')
            ->assertSee('منى المشتكية')
            ->assertSee('+974 5512 3456')
            ->assertSee(route('admin.support.reply', $ticket), false)
            ->assertSee(route('admin.support.status', $ticket), false)
            ->assertSee(route('admin.support.assign', $ticket), false)
            ->getContent();

        expect(substr_count($html, '<h1'))->toBe(1);
    })->with(['super_admin', 'moderator', 'support']);

    it('hides the reply, status and assign forms from staff who can only read tickets', function (): void {
        $ticket = supportTicketFor($this->requester);
        Role::findByName('support', 'web')->syncPermissions(['support.view']);
        actingAs(User::factory()->create()->assignRole('support'));

        $this->get(route('admin.support.show', $ticket))
            ->assertOk()
            ->assertSee('زر النشر لا يستجيب.')
            ->assertSee('غير مُسنَدة')
            ->assertDontSee(route('admin.support.reply', $ticket), false)
            ->assertDontSee(route('admin.support.status', $ticket), false)
            ->assertDontSee(route('admin.support.assign', $ticket), false)
            ->assertDontSee(route('admin.users.show', $this->requester), false)
            ->assertDontSee('سجل التذكرة');
    });

    it('shows the assignee instead of the assign button once the ticket is mine', function (): void {
        $ticket = supportTicketFor($this->requester, ['assigned_to' => $this->admin->id]);
        actingAs($this->admin);

        $this->get(route('admin.support.show', $ticket))
            ->assertOk()
            ->assertSee('التذكرة مُسنَدة إليك')
            ->assertDontSee(route('admin.support.assign', $ticket), false);
    });

    it('lists at most five other tickets of the requester, newest first, without the open one', function (): void {
        $ticket = supportTicketFor($this->requester);
        foreach (range(1, 7) as $i) {
            $this->travel(1)->minutes();
            supportTicketFor($this->requester, ['subject' => "تذكرة سابقة {$i}", 'status' => SupportTicketStatus::CLOSED]);
        }
        supportTicketFor(User::factory()->create(), ['subject' => 'تذكرة مستخدم آخر']);
        actingAs($this->admin);

        $html = $this->get(route('admin.support.show', $ticket))
            ->assertOk()
            ->assertSee('تذاكر أخرى للمستخدم')
            ->assertSee('تذكرة سابقة 7')
            ->assertSee('تذكرة سابقة 3')
            ->assertDontSee('تذكرة سابقة 2')
            ->assertDontSee('تذكرة مستخدم آخر')
            ->getContent();

        expect(substr_count($html, 'href="' . route('admin.support.show', $ticket) . '"'))->toBe(0);
    });

    it('renders a guest ticket without a requester account', function (): void {
        $ticket = supportTicketFor(null, ['email' => 'guest@example.com']);
        actingAs($this->admin);

        $this->get(route('admin.support.show', $ticket))
            ->assertOk()
            ->assertSee('guest@example.com')
            ->assertDontSee('تذاكر أخرى للمستخدم');
    });

    it('shows the ticket audit trail to staff who can read the activity log', function (): void {
        $ticket = supportTicketFor($this->requester);
        activity('admin')->performedOn($ticket)->causedBy($this->admin)->event('admin.support.assign')->log('admin.support.assign');
        actingAs($this->admin);

        $this->get(route('admin.support.show', $ticket))
            ->assertOk()
            ->assertSee('سجل التذكرة')
            ->assertSee('إسناد التذكرة');
    });

    it('keeps the query count flat with 1 or 25 replies, other tickets and audit rows', function (): void {
        $ticket = supportTicketFor($this->requester);
        actingAs($this->admin);
        $uri = route('admin.support.show', $ticket);

        giveTicketHistory($ticket, $this->admin, 1);
        $few = $this->steadyQueryCount($uri);

        giveTicketHistory($ticket, $this->admin, 24);
        $many = $this->steadyQueryCount($uri);

        expect($many)->toBe($few)->toBeLessThanOrEqual(10);
    });
});

/** Messages from both sides and reports filed on the conversation. */
function giveConversationHistory(Conversation $conversation, int $count): void
{
    foreach (range(1, $count) as $i) {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $i % 2 === 0 ? $conversation->buyer_id : $conversation->seller_id,
            'body' => "رسالة رقم {$i}",
        ]);
        Report::factory()->create([
            'target_type' => ReportTarget::CONVERSATION->value,
            'target_id' => $conversation->id,
        ]);
    }
}

describe('conversations.show', function (): void {
    beforeEach(function (): void {
        $seller = User::factory()->create(['full_name' => 'خالد البائع']);
        $this->ad = $this->makeAd($seller, ['title' => 'كاميرا كانون', 'price' => '1500.00']);
        $this->conversation = Conversation::factory()->create([
            'ad_id' => $this->ad->id,
            'seller_id' => $seller->id,
            'buyer_id' => User::factory()->create(['full_name' => 'ليلى المشترية'])->id,
        ]);
    });

    it('renders in the shell for every role that can open conversations, with one h1 and the #250 transcript', function (string $role): void {
        giveConversationHistory($this->conversation, 2);
        actingAs(User::factory()->create()->assignRole($role));

        $html = $this->get(route('admin.conversations.show', $this->conversation))
            ->assertOk()
            ->assertSee('data-qb-page', false)
            ->assertSee('محادثة حول: كاميرا كانون')
            ->assertSee('رسالة رقم 1')
            ->assertSee('رسالة رقم 2')
            ->assertSee('خالد البائع')
            ->assertSee('ليلى المشترية')
            ->assertSee('1500.00 QAR')
            ->assertSee(route('admin.ads.show', $this->ad), false)
            ->assertSee('البلاغات على المحادثة')
            ->getContent();

        expect(substr_count($html, '<h1'))->toBe(1);
    })->with(['super_admin', 'moderator']);

    it('leaves the ad link and the reports card to staff allowed to open them', function (): void {
        giveConversationHistory($this->conversation, 1);
        Role::findByName('support', 'web')->syncPermissions(['conversations.view']);
        actingAs(User::factory()->create()->assignRole('support'));

        $this->get(route('admin.conversations.show', $this->conversation))
            ->assertOk()
            ->assertSee('رسالة رقم 1')
            ->assertSee('كاميرا كانون')
            ->assertDontSee(route('admin.ads.show', $this->ad), false)
            ->assertDontSee('البلاغات على المحادثة')
            ->assertDontSee(route('admin.reports.index'), false);
    });

    it('shows the newest five reports on the conversation and links the rest', function (): void {
        giveConversationHistory($this->conversation, 7);
        Report::factory()->create(['target_type' => ReportTarget::AD->value, 'target_id' => $this->ad->id]);
        actingAs($this->admin);

        $html = $this->get(route('admin.conversations.show', $this->conversation))->assertOk()->getContent();

        expect(substr_count($html, 'href="' . url('admin/reports') . '/'))->toBe(5)
            ->and($html)->toContain(e(route('admin.reports.index', ['q' => $this->conversation->id])));
    });

    it('shows an empty state for a conversation whose ad was deleted', function (): void {
        $this->ad->delete();
        actingAs($this->admin);

        $this->get(route('admin.conversations.show', $this->conversation->refresh()))
            ->assertOk()
            ->assertSee('الإعلان لم يعد متاحاً.')
            ->assertSee('لا توجد بلاغات على هذه المحادثة.');
    });

    it('keeps the query count flat with 1 or 25 messages and reports', function (): void {
        actingAs($this->admin);
        $uri = route('admin.conversations.show', $this->conversation);

        giveConversationHistory($this->conversation, 1);
        $few = $this->steadyQueryCount($uri);

        giveConversationHistory($this->conversation, 24);
        $many = $this->steadyQueryCount($uri);

        expect($many)->toBe($few)->toBeLessThanOrEqual(14);
    });
});
