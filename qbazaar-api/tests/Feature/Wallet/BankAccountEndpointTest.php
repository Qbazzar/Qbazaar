<?php

declare(strict_types=1);

use App\Exceptions\ErrorCode;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, ManagesMoney::class);

beforeEach(function (): void {
    $this->seller = User::factory()->create();
});

it('saves the first account as the default and only ever returns the IBAN masked', function (): void {
    $response = $this->addBankAccount($this->seller, 'QA58 DOHB 0000 1234 5678 90AB CDEF G', ['bank_name' => 'Doha Bank'])
        ->assertCreated()
        ->assertJsonPath('data.holder_name', 'Ahmed Al-Ali')
        ->assertJsonPath('data.iban_masked', 'QA** **** DEFG')
        ->assertJsonPath('data.bank_name', 'Doha Bank')
        ->assertJsonPath('data.is_default', true)
        ->assertJsonMissingPath('data.iban');

    expect($response->getContent())->not->toContain('DOHB0000');

    $stored = DB::table('bank_accounts')->value('iban');
    expect($stored)->not->toContain('QA58')
        ->and(BankAccount::query()->sole()->iban)->toBe('QA58DOHB00001234567890ABCDEFG');
});

it('keeps exactly one default account', function (): void {
    $this->addBankAccount($this->seller)->assertCreated();
    $this->addBankAccount($this->seller, 'SA0380000000608010167519', ['is_default' => true])->assertCreated();

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/bank-accounts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.iban_masked', 'SA** **** 7519')
        ->assertJsonPath('data.0.is_default', true)
        ->assertJsonPath('data.1.is_default', false);
});

it('promotes the remaining account when the default is deleted', function (): void {
    $first = $this->addBankAccount($this->seller)->json('data.id');
    $this->addBankAccount($this->seller, 'SA0380000000608010167519')->assertCreated();

    $this->actingAs($this->seller, 'sanctum')->deleteJson('/api/v1/account/bank-accounts/' . $first)->assertNoContent();

    expect(BankAccount::query()->sole()->is_default)->toBeTrue();
});

it('rejects an IBAN with a wrong checksum', function (): void {
    $this->addBankAccount($this->seller, 'QA58DOHB00001234567890ABCDEFH')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('refuses the same IBAN twice for one user', function (): void {
    $this->addBankAccount($this->seller)->assertCreated();

    $this->addBankAccount($this->seller, 'qa58dohb00001234567890abcdefg')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::BANK_ACCOUNT_DUPLICATE->value);
});

it('caps the number of accounts', function (): void {
    config(['qbazaar.wallet.max_bank_accounts' => 1]);
    $this->addBankAccount($this->seller)->assertCreated();

    $this->addBankAccount($this->seller, 'SA0380000000608010167519')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::BANK_ACCOUNT_LIMIT_REACHED->value);
});

it('hides other users\' accounts', function (): void {
    $id = $this->addBankAccount($this->seller)->json('data.id');
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')->getJson('/api/v1/account/bank-accounts')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($stranger, 'sanctum')->deleteJson('/api/v1/account/bank-accounts/' . $id)
        ->assertNotFound()
        ->assertJsonPath('error.code', ErrorCode::BANK_ACCOUNT_NOT_FOUND->value);

    expect(BankAccount::query()->count())->toBe(1);
});
