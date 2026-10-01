<?php

declare(strict_types=1);

use App\Enums\ModerationRuleType;
use App\Models\ModerationRule;
use App\Services\Moderation\ModerationRulesService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies a newly banned word to the next job in a long-running worker', function (): void {
    ModerationRule::query()->create(['type' => ModerationRuleType::BANNED_WORD, 'value' => 'oldword', 'is_active' => true]);

    expect(app(ModerationRulesService::class)->containsBannedWords('contains freshword'))->toBe([]);

    ModerationRule::query()->create(['type' => ModerationRuleType::BANNED_WORD, 'value' => 'freshword', 'is_active' => true]);

    // What the queue worker does between two jobs.
    app()->forgetScopedInstances();

    expect(app(ModerationRulesService::class)->containsBannedWords('contains freshword'))->toBe(['freshword']);
});
