<?php

declare(strict_types=1);

use App\Enums\ChatMessageKey;
use App\Enums\MessageType;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensConversations;

uses(RefreshDatabase::class, OpensConversations::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed([RolesAndPermissionsSeeder::class, LocationSeeder::class, CategorySeeder::class]);
    $this->admin = User::factory()->create()->assignRole('super_admin');
    actingAs($this->admin);
    $this->conversation = Conversation::factory()->create();
    $this->openConversation($this->conversation);
});

function say(Conversation $conversation, string $senderId, string $body, array $extra = []): Message
{
    return Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $senderId,
        'type' => MessageType::TEXT,
        'body' => $body,
        ...$extra,
    ]);
}

function withAvatar(User $user): User
{
    $user->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('avatar');

    return $user;
}

function queriesFor(string $uri): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($uri)->assertOk();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

/** Warms caches (permissions, etc.) so the two measurements compare like with like. */
function steadyQueries(string $uri): int
{
    queriesFor($uri);

    return queriesFor($uri);
}

it('shows initials per sender and the avatar once per run of messages', function (): void {
    $c = $this->conversation;
    say($c, $c->buyer_id, 'one');
    say($c, $c->buyer_id, 'two');
    say($c, $c->seller_id, 'three');

    $html = $this->get("/admin/conversations/{$c->id}")->assertOk()->getContent();

    // Header avatars sit beside the name (decorative); each sender run gets one labelled avatar.
    expect(substr_count($html, 'role="img"'))->toBe(2)
        ->and(substr_count($html, 'aria-hidden="true">'))->toBeGreaterThanOrEqual(2);
});

it('renders the real avatar photo with lazy loading and the name as alt text', function (): void {
    $c = $this->conversation;
    withAvatar($c->buyer);
    say($c, $c->buyer_id, 'hello');

    $html = $this->get("/admin/conversations/{$c->id}")->getContent();

    expect($html)->toContain('loading="lazy"')
        ->and($html)->toContain('alt="' . e($c->buyer->full_name) . '"')
        ->and($html)->toContain('alt=""');
});

it('localises deal messages in arabic and english with formatted amounts', function (): void {
    $c = $this->conversation;
    say($c, $c->buyer_id, 'Buy request: 1 pcs for 5900.00 QAR', [
        'type' => MessageType::PURCHASE_REQUEST,
        'message_key' => ChatMessageKey::PURCHASE_REQUEST_CREATED->value,
        'params' => ['quantity' => 1, 'amount' => '5900', 'currency' => 'QAR'],
    ]);
    say($c, $c->seller_id, 'Purchase completed', [
        'type' => MessageType::SYSTEM,
        'message_key' => ChatMessageKey::PURCHASE_REQUEST_PAID->value,
    ]);

    app()->setLocale('ar');
    $this->get("/admin/conversations/{$c->id}")
        ->assertSee('طلب شراء: 1 قطعة بمبلغ 5900.00 QAR')
        ->assertSee('تمت عملية الشراء')
        ->assertDontSee('Purchase completed');

    app()->setLocale('en');
    $this->get("/admin/conversations/{$c->id}")
        ->assertSee('Buy request: 1 pcs for 5900.00 QAR')
        ->assertSee('Purchase completed');
});

it('escapes user written text', function (): void {
    $c = $this->conversation;
    say($c, $c->buyer_id, '<script>alert(1)</script><b>x</b>');

    $this->get("/admin/conversations/{$c->id}")
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('runs a fixed number of queries across many distinct senders with avatars', function (): void {
    $c = $this->conversation;
    $uri = "/admin/conversations/{$c->id}";
    say($c, withAvatar(User::factory()->create())->id, 'a');
    say($c, User::factory()->create()->id, 'b');
    $few = steadyQueries($uri);

    foreach (range(1, 25) as $i) {
        $sender = User::factory()->create();
        if ($i % 3 === 0) {
            withAvatar($sender);
        }
        say($c, $sender->id, "m{$i}");
    }

    expect(steadyQueries($uri))->toBe($few);
});

it('opens long threads on the newest page and keeps older pages reachable', function (): void {
    $c = $this->conversation;
    foreach (range(1, 105) as $i) {
        $this->travelTo(now()->startOfDay()->addSeconds($i));
        say($c, $c->buyer_id, sprintf('msg-%03d', $i));
    }

    $this->get("/admin/conversations/{$c->id}")
        ->assertSee('msg-105')
        ->assertSee('msg-101')
        ->assertDontSee('msg-100')
        ->assertSee('page=1', false);

    $this->get("/admin/conversations/{$c->id}?page=1")
        ->assertSee('msg-001')
        ->assertDontSee('msg-105');
});

it('does not add queries per row on the users list', function (): void {
    User::factory()->count(2)->create();
    $few = steadyQueries('/admin/users');

    User::factory()->count(15)->create()->each(fn (User $user) => withAvatar($user));

    expect(steadyQueries('/admin/users'))->toBe($few);
});

it('does not add queries per row on the conversations list', function (): void {
    Conversation::factory()->count(2)->create();
    $few = steadyQueries('/admin/conversations');

    Conversation::factory()->count(8)->create()->each(function (Conversation $conversation): void {
        withAvatar($conversation->buyer);
        withAvatar($conversation->seller);
    });

    expect(steadyQueries('/admin/conversations'))->toBe($few);
});

it('does not add queries per row on the ads list with covers and seller avatars', function (): void {
    $make = function (): void {
        $ad = Ad::factory()->active()->create(['user_id' => withAvatar(User::factory()->create())->id]);
        $ad->addMedia(UploadedFile::fake()->image('p.jpg', 20, 20))->toMediaCollection('images');
    };
    $make();
    $few = steadyQueries('/admin/ads');

    foreach (range(1, 8) as $ignored) {
        $make();
    }

    expect(steadyQueries('/admin/ads'))->toBe($few);
});

it('does not add queries per row on the activity log with avatar causers', function (): void {
    $log = fn (User $user) => activity()->causedBy($user)->log('did something');
    $log(withAvatar(User::factory()->create()));
    $few = steadyQueries('/admin/activity');

    foreach (range(1, 8) as $ignored) {
        $log(withAvatar(User::factory()->create()));
    }

    expect(steadyQueries('/admin/activity'))->toBe($few);
});

it('hides the user and ad links from staff without users.view and ads.view', function (): void {
    $c = $this->conversation;
    $c->update(['ad_id' => Ad::factory()->active()->create()->id]);
    Role::findByName('moderator', 'web')->revokePermissionTo(['users.view', 'ads.view']);
    actingAs(User::factory()->create()->assignRole('moderator'));
    $this->openConversation($c);

    $this->get("/admin/conversations/{$c->id}")
        ->assertOk()
        ->assertDontSee('/admin/users/', false)
        ->assertDontSee('/admin/ads/', false);

    actingAs($this->admin);
    $this->get("/admin/conversations/{$c->id}")
        ->assertSee("/admin/users/{$c->buyer_id}", false)
        ->assertSee("/admin/ads/{$c->ad_id}", false);
});

it('shows localised role names and an isolated phone number in the users list', function (): void {
    User::factory()->create(['phone' => '+97439000000'])->assignRole('moderator');
    app()->setLocale('ar');

    $this->get('/admin/users')
        ->assertSee('مشرف')
        ->assertSee('dir="ltr"', false)
        ->assertSee('+974 3900 0000');
});

it('shows localised role names on the roles index', function (): void {
    app()->setLocale('ar');

    $this->get('/admin/roles')
        ->assertSee('مدير عام')
        ->assertSee('مشرف')
        ->assertSee('دعم فني')
        ->assertDontSee('>super_admin<', false);
});
