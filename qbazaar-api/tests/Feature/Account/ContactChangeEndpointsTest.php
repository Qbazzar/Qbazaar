<?php

declare(strict_types=1);

use App\Actions\Account\RequestEmailChangeAction;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\Account\ConfirmEmailChangeNotification;
use App\Notifications\Account\EmailChangedNotification;
use App\Notifications\Account\ReauthCodeNotification;
use App\Notifications\OtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    config()->set('qbazaar.otp.fixed_code', '654321');

    $this->user = User::factory()->create([
        'email' => 'old@example.qa',
        'phone' => '+97455000001',
        'phone_verified' => true,
    ]);
    Sanctum::actingAs($this->user, ['*']);
});

function reauthCode(User $user): string
{
    postJson('/api/v1/account/reauth-code')->assertStatus(202);

    $code = null;
    Notification::assertSentTo($user, ReauthCodeNotification::class, function (ReauthCodeNotification $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return (string) $code;
}

function emailConfirmUrl(): string
{
    $url = null;
    Notification::assertSentOnDemand(ConfirmEmailChangeNotification::class, function (ConfirmEmailChangeNotification $notification) use (&$url): bool {
        $url = $notification->confirmUrl;

        return true;
    });

    return (string) $url;
}

it('emails a step-up code to the current address and enforces a cooldown', function (): void {
    postJson('/api/v1/account/reauth-code')
        ->assertStatus(202)
        ->assertJsonPath('data.expires_in', 600)
        ->assertJsonPath('data.can_resend_in', 60);

    Notification::assertSentTo($this->user, ReauthCodeNotification::class);

    postJson('/api/v1/account/reauth-code')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'AUTH_006');
});

it('changes the email through the link sent to the new address and tells the old one', function (): void {
    $code = reauthCode($this->user);

    postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => $code])
        ->assertStatus(202)
        ->assertJsonPath('data.pending_email', 'new@example.qa');

    expect($this->user->fresh()->email)->toBe('old@example.qa');

    Notification::assertSentOnDemand(
        ConfirmEmailChangeNotification::class,
        fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'new@example.qa',
    );

    getJson(emailConfirmUrl())
        ->assertOk()
        ->assertJsonPath('data.email', 'new@example.qa');

    $fresh = $this->user->fresh();
    expect($fresh->email)->toBe('new@example.qa')
        ->and($fresh->email_verified)->toBeTrue();

    Notification::assertSentOnDemand(
        EmailChangedNotification::class,
        fn (EmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'old@example.qa'
            && ! str_contains($notification->maskedNewEmail, 'new@'),
    );
});

it('makes the confirmation link single-use', function (): void {
    postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => reauthCode($this->user)])->assertStatus(202);
    $url = emailConfirmUrl();

    getJson($url)->assertOk();

    getJson($url)
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'ACCOUNT_002');
});

it('redirects a browser to the web result page', function (): void {
    postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => reauthCode($this->user)])->assertStatus(202);
    $url = emailConfirmUrl();
    $web = rtrim((string) config('qbazaar.web_url'), '/');

    get($url, ['Accept' => 'text/html'])->assertRedirect($web . '/account/email-change/result?status=success');
    get($url, ['Accept' => 'text/html'])->assertRedirect($web . '/account/email-change/result?status=ACCOUNT_002');
});

it('refuses the change when the new email was taken in the meantime', function (): void {
    postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => reauthCode($this->user)])->assertStatus(202);
    User::factory()->create(['email' => 'new@example.qa']);

    getJson(emailConfirmUrl())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AUTH_007');

    expect($this->user->fresh()->email)->toBe('old@example.qa');
});

it('rejects a tampered confirmation link', function (): void {
    $url = URL::temporarySignedRoute('api.v1.account.email.confirm', now()->addHour(), [
        'user' => $this->user->id,
        'email' => 'new@example.qa',
        'from' => RequestEmailChangeAction::fingerprint('old@example.qa'),
    ]);

    getJson(str_replace('new%40example.qa', 'evil%40example.qa', $url))->assertForbidden();

    expect($this->user->fresh()->email)->toBe('old@example.qa');
});

it('requires a valid step-up code for an email change', function (): void {
    reauthCode($this->user);

    postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => '000000'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    Notification::assertNothingSentTo(new AnonymousNotifiable, ConfirmEmailChangeNotification::class);
});

it('burns the step-up code after three wrong attempts', function (): void {
    $code = reauthCode($this->user);

    foreach (range(1, 3) as $attempt) {
        postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => '000000'])->assertStatus(422);
    }

    postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');
});

it('validates the new email before spending the code', function (): void {
    $code = reauthCode($this->user);
    User::factory()->create(['email' => 'taken@example.qa']);

    postJson('/api/v1/account/email', ['email' => 'taken@example.qa', 'reauth_code' => $code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email'], 'error.details');

    postJson('/api/v1/account/email', ['email' => 'old@example.qa', 'reauth_code' => $code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email'], 'error.details');

    postJson('/api/v1/account/email', ['email' => 'new@example.qa', 'reauth_code' => $code])->assertStatus(202);
});

it('changes the phone after the OTP sent to the new number', function (): void {
    postJson('/api/v1/account/phone', ['phone' => '+97455000002', 'reauth_code' => reauthCode($this->user)])
        ->assertStatus(202)
        ->assertJsonPath('data.sent_to', '+97455000002');

    Notification::assertSentOnDemand(OtpNotification::class, fn (OtpNotification $notification): bool => $notification->phone === '+97455000002');
    expect(OtpCode::query()->where('recipient', '+97455000002')->value('purpose'))->toBe(OtpPurpose::PHONE_CHANGE);

    postJson('/api/v1/account/phone/verify', ['code' => '111111'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AUTH_005');

    postJson('/api/v1/account/phone/verify', ['code' => '654321'])
        ->assertOk()
        ->assertJsonPath('data.phone', '+97455000002');

    expect($this->user->fresh()->phone)->toBe('+97455000002')
        ->and($this->user->fresh()->phone_verified)->toBeTrue();
});

it('refuses to verify a phone change that was never requested', function (): void {
    postJson('/api/v1/account/phone/verify', ['code' => '654321'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AUTH_005');
});

it('refuses a phone that already belongs to another account', function (): void {
    User::factory()->create(['phone' => '+97455000003']);

    postJson('/api/v1/account/phone', ['phone' => '+97455000003', 'reauth_code' => reauthCode($this->user)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone'], 'error.details');
});

it('does not let a phone verification code confirm a phone change', function (): void {
    postJson('/api/v1/account/phone', ['phone' => '+97455000002', 'reauth_code' => reauthCode($this->user)])->assertStatus(202);
    OtpCode::query()->where('recipient', '+97455000002')->update(['purpose' => OtpPurpose::PHONE_VERIFICATION->value]);

    postJson('/api/v1/account/phone/verify', ['code' => '654321'])->assertStatus(422);

    expect($this->user->fresh()->phone)->toBe('+97455000001');
});

it('requires authentication for every change endpoint', function (): void {
    $this->refreshApplication();

    postJson('/api/v1/account/reauth-code')->assertStatus(401);
    postJson('/api/v1/account/email', ['email' => 'a@b.qa', 'reauth_code' => '123456'])->assertStatus(401);
    postJson('/api/v1/account/phone', ['phone' => '+97455000002', 'reauth_code' => '123456'])->assertStatus(401);
    postJson('/api/v1/account/phone/verify', ['code' => '123456'])->assertStatus(401);
});
