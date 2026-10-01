<?php

declare(strict_types=1);

use App\Actions\Auth\RegisterUserAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\DeviceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

function registerViaAction(string $email, string $phone): void
{
    app(RegisterUserAction::class)->execute(
        ['full_name' => 'Ahmed Al-Ali', 'email' => $email, 'phone' => $phone],
        new DeviceContext(hash: str_repeat('b', 64), label: 'ios', ip: '127.0.0.1'),
    );
}

it('answers AUTH_007 when a concurrent sign-up already took the email', function (): void {
    User::factory()->create(['email' => 'owner@example.qa']);

    expect(fn () => registerViaAction('Owner@example.qa', '+97455000111'))
        ->toThrow(fn (DomainException $e) => expect($e->errorCode)->toBe(ErrorCode::AUTH_EMAIL_EXISTS));
});

it('answers AUTH_008 when a concurrent sign-up already took the phone', function (): void {
    User::factory()->create(['phone' => '+97455000111']);

    expect(fn () => registerViaAction('new@example.qa', '+97455000111'))
        ->toThrow(fn (DomainException $e) => expect($e->errorCode)->toBe(ErrorCode::AUTH_PHONE_EXISTS));

    Notification::assertNothingSent();
});
