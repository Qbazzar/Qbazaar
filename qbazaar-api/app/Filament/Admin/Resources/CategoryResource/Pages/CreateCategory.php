<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CategoryResource\Pages;

use App\Filament\Admin\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * After-save hook so the public category cache picks up newly created
     * rows without a deploy or a TTL wait.
     */
    protected function afterCreate(): void
    {
        CategoryResource::flushCache();
    }
}
