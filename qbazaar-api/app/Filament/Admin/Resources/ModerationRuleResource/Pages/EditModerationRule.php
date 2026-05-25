<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ModerationRuleResource\Pages;

use App\Filament\Admin\Resources\ModerationRuleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditModerationRule extends EditRecord
{
    protected static string $resource = ModerationRuleResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
