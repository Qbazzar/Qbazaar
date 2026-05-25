<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OfferResource\Pages;

use App\Filament\Admin\Resources\OfferResource;
use Filament\Resources\Pages\ListRecords;

class ListOffers extends ListRecords
{
    protected static string $resource = OfferResource::class;
}
