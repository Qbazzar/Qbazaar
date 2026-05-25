<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ModerationRuleResource\Pages;

use App\Filament\Admin\Resources\ModerationRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModerationRule extends CreateRecord
{
    protected static string $resource = ModerationRuleResource::class;
}
