<?php

declare(strict_types=1);

use App\Enums\ChatMessageKey;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed([RolesAndPermissionsSeeder::class, LocationSeeder::class, CategorySeeder::class]);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
    $this->conversation = Conversation::factory()->create();
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

it('shows initials per sender and the avatar once per run of messages', function (): void {
    $c = $this->conversation;
    say($c, $c->buyer_id, 'one');
    say($c, $c->buyer_id, 'two');
    say($c, $c->seller_id, 'three');

    $html = $this->get("/admin/conversations/{$c->id}")->assertOk()->getContent();

    // Header (buyer, seller) + one per run (buyer run, seller run) = 4 avatars.
    expect(substr_count($html, 'role="img"'))->toBe(4);
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

it('runs a fixed number of queries however many messages there are', function (): void {
    $c = $this->conversation;
    $count = function () use ($c): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/admin/conversations/{$c->id}")->assertOk();

        return count(DB::getQueryLog());
    };

    say($c, $c->buyer_id, 'a');
    say($c, $c->seller_id, 'b');
    $count(); // warm the permission cache
    $few = $count();

    foreach (range(1, 30) as $i) {
        say($c, $i % 2 ? $c->buyer_id : $c->seller_id, "m{$i}");
    }

    expect($count())->toBe($few);
});

it('does not add queries per row on the users list', function (): void {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/admin/users')->assertOk();

        return count(DB::getQueryLog());
    };

    User::factory()->count(2)->create();
    $count(); // warm the permission cache
    $few = $count();
    User::factory()->count(15)->create();

    expect($count())->toBe($few);
});

it('does not add queries per row on the conversations list', function (): void {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/admin/conversations')->assertOk();

        return count(DB::getQueryLog());
    };

    Conversation::factory()->count(2)->create();
    $count(); // warm the permission cache
    $few = $count();
    Conversation::factory()->count(8)->create();

    expect($count())->toBe($few);
});

it('shows localised role names and an isolated phone number in the users list', function (): void {
    User::factory()->create(['phone' => '+97439000000'])->assignRole('moderator');
    app()->setLocale('ar');

    $this->get('/admin/users')
        ->assertSee('مشرف')
        ->assertSee('dir="ltr"', false)
        ->assertSee('+974 3900 0000');
});
