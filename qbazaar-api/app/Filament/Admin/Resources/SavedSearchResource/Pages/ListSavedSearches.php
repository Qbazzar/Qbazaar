<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SavedSearchResource\Pages;

use App\Filament\Admin\Resources\SavedSearchResource;
use Filament\Resources\Pages\ListRecords;

class ListSavedSearches extends ListRecords
{
    protected static string $resource = SavedSearchResource::class;
}
