<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\OtpCode;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\EmailSignInCodeNotification;
use App\Notifications\WelcomeNotification;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();
});

function issueEmailCode(string $email): string
{
    return app(OtpService::class)->issue($email, OtpPurpose::EMAIL_SIGN_IN)->rawCode;
}

/**
 * @return array<string, mixed>
 */
function signUpDetails(array $overrides = []): array
{
    return [
        'full_name' => 'Ahmed Al-Ali',
        'phone' => '+97455123456',
        'account_type' => 'private',
        'language' => 'en',
        'accepted_terms' => true,
        ...$overrides,
    ];
}

describe('POST /auth/email-otp/send', function (): void {
    it('mails a hashed 10-minute code to an address with no account', function (): void {
        postJson('/api/v1/auth/email-otp/send', ['email' => 'New@Example.qa'])
            ->assertStatus(202)
            ->assertJsonPath('data.sent_to', 'new@example.qa')
            ->assertJsonPath('data.expires_in', 600)
            ->assertJsonPath('data.can_resend_in', 60);

        $row = OtpCode::query()->where('recipient', 'new@example.qa')->sole();

        expect($row->purpose)->toBe(OtpPurpose::EMAIL_SIGN_IN)
            ->and(strlen($row->code_hash))->toBeGreaterThan(6);

        Notification::assertSentOnDemand(
            EmailSignInCodeNotification::class,
            fn (EmailSignInCodeNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'new@example.qa'
                && preg_match('/^\d{6}$/', $notification->code) === 1,
        );
    });

    it('mails the code to a registered user in their language', function (): void {
        $user = User::factory()->create(['email' => 'owner@example.qa', 'language' => 'en']);

        postJson('/api/v1/auth/email-otp/send', ['email' => 'owner@example.qa'])->assertStatus(202);

        Notification::assertSentTo($user, EmailSignInCodeNotification::class, fn (EmailSignInCodeNotification $n): bool => $n->language === 'en');
    });

    it('refuses a second send inside the cooldown', function (): void {
        postJson('/api/v1/auth/email-otp/send', ['email' => 'new@example.qa'])->assertStatus(202);

        postJson('/api/v1/auth/email-otp/send', ['email' => 'new@example.qa'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_006');

        Notification::assertSentOnDemandTimes(EmailSignInCodeNotification::class, 1);
    });

    it('validates the email', function (): void {
        postJson('/api/v1/auth/email-otp/send', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });
});

describe('POST /auth/email-otp/verify', function (): void {
    it('signs an existing user in, marks the email verified and spends the code', function (): void {
        $user = User::factory()->create(['email' => 'owner@example.qa', 'email_verified' => false]);
        $code = issueEmailCode('owner@example.qa');

        postJson('/api/v1/auth/email-otp/verify', ['email' => 'owner@example.qa', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.tokens.token_type', 'Bearer');

        expect($user->fresh()->email_verified)->toBeTrue()
            ->and($user->fresh()->last_login_at)->not->toBeNull()
            ->and(app(OtpService::class)->activeRowFor('owner@example.qa', OtpPurpose::EMAIL_SIGN_IN))->toBeNull();

        postJson('/api/v1/auth/email-otp/verify', ['email' => 'owner@example.qa', 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_005');
    });

    it('asks for sign-up details without spending the code, then creates a passwordless account', function (): void {
        $code = issueEmailCode('new@example.qa');

        postJson('/api/v1/auth/email-otp/verify', ['email' => 'new@example.qa', 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_011')
            ->assertJsonPath('error.details.email', 'new@example.qa');

        expect(User::query()->count())->toBe(0);

        postJson('/api/v1/auth/email-otp/verify', [
            'email' => 'new@example.qa',
            'code' => $code,
            ...signUpDetails(['account_type' => 'business']),
        ], ['X-Device-Id' => 'device-0123456789abcdef'])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'new@example.qa')
            ->assertJsonPath('data.user.account_type', 'business')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.phone_verified', false)
            ->assertJsonPath('data.user.language', 'en');

        $user = User::query()->where('email', 'new@example.qa')->sole();

        expect($user->password)->toBeNull()
            ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1);

        Notification::assertSentTo($user, WelcomeNotification::class);
    });

    it('does not reveal whether an account exists to someone without the code', function (): void {
        issueEmailCode('new@example.qa');

        postJson('/api/v1/auth/email-otp/verify', ['email' => 'new@example.qa', 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_005');
    });

    it('burns the code after five wrong attempts', function (): void {
        User::factory()->create(['email' => 'owner@example.qa']);
        $code = issueEmailCode('owner@example.qa');
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $attempt) {
            postJson('/api/v1/auth/email-otp/verify', ['email' => 'owner@example.qa', 'code' => $wrong])
                ->assertJsonPath('error.code', 'AUTH_005');
        }

        expect(app(OtpService::class)->activeRowFor('owner@example.qa', OtpPurpose::EMAIL_SIGN_IN))->toBeNull();
    });

    it('rejects a code older than ten minutes', function (): void {
        User::factory()->create(['email' => 'owner@example.qa']);
        $code = issueEmailCode('owner@example.qa');

        travel(11)->minutes();

        postJson('/api/v1/auth/email-otp/verify', ['email' => 'owner@example.qa', 'code' => $code])
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'AUTH_004');
    });

    it('does not accept a phone verification code', function (): void {
        User::factory()->create(['email' => 'owner@example.qa']);
        $code = app(OtpService::class)->issue('owner@example.qa')->rawCode;

        postJson('/api/v1/auth/email-otp/verify', ['email' => 'owner@example.qa', 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_005');
    });

    it('refuses a suspended account', function (): void {
        User::factory()->create(['email' => 'owner@example.qa', 'status' => UserStatus::SUSPENDED->value]);
        $code = issueEmailCode('owner@example.qa');

        postJson('/api/v1/auth/email-otp/verify', ['email' => 'owner@example.qa', 'code' => $code])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_002');
    });

    it('requires the whole set of sign-up details once one is sent', function (): void {
        postJson('/api/v1/auth/email-otp/verify', [
            'email' => 'new@example.qa',
            'code' => '123456',
            'full_name' => 'Ahmed Al-Ali',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['phone', 'account_type', 'accepted_terms'], 'error.details');
    });

    it('rejects a phone that another account already uses', function (): void {
        User::factory()->create(['phone' => '+97455123456']);

        postJson('/api/v1/auth/email-otp/verify', [
            'email' => 'new@example.qa',
            'code' => '123456',
            ...signUpDetails(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'], 'error.details');
    });
});
