<?php

declare(strict_types=1);

use App\Enums\ModerationRuleLanguage;
use App\Enums\ModerationRuleType;
use App\Filament\Admin\Resources\ModerationRuleResource\Pages\CreateModerationRule;
use App\Filament\Admin\Resources\ModerationRuleResource\Pages\EditModerationRule;
use App\Filament\Admin\Resources\ModerationRuleResource\Pages\ListModerationRules;
use App\Models\ModerationRule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
});

it('creates a moderation rule', function (): void {
    Livewire::test(CreateModerationRule::class)
        ->fillForm([
            'type' => ModerationRuleType::BANNED_WORD->value,
            'language' => ModerationRuleLanguage::EN->value,
            'value' => 'spamword',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = ModerationRule::query()->where('value', 'spamword')->first();
    expect($rule)->not->toBeNull();
    expect($rule->type)->toBe(ModerationRuleType::BANNED_WORD);
});

it('edits a moderation rule', function (): void {
    $rule = ModerationRule::create([
        'type' => ModerationRuleType::BLOCKED_DOMAIN->value,
        'language' => ModerationRuleLanguage::ANY->value,
        'value' => 'bad.example',
        'is_active' => true,
    ]);

    Livewire::test(EditModerationRule::class, ['record' => $rule->getKey()])
        ->fillForm([
            'type' => ModerationRuleType::BLOCKED_DOMAIN->value,
            'language' => ModerationRuleLanguage::ANY->value,
            'value' => 'worse.example',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $rule->refresh();
    expect($rule->value)->toBe('worse.example');
    expect($rule->is_active)->toBeFalse();
});

it('deletes a moderation rule', function (): void {
    $rule = ModerationRule::create([
        'type' => ModerationRuleType::BANNED_WORD->value,
        'language' => ModerationRuleLanguage::ANY->value,
        'value' => 'deleteme',
        'is_active' => true,
    ]);

    Livewire::test(ListModerationRules::class)
        ->callTableAction('delete', $rule);

    expect(ModerationRule::query()->whereKey($rule->getKey())->exists())->toBeFalse();
});
