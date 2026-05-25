<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ModerationRuleResource\Pages;

use App\Filament\Admin\Resources\ModerationRuleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModerationRules extends ListRecords
{
    protected static string $resource = ModerationRuleResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
