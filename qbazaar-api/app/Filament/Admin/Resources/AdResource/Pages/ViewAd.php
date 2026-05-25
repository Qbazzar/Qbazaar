<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdResource\Pages;

use App\Filament\Admin\Resources\AdResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAd extends ViewRecord
{
    protected static string $resource = AdResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
