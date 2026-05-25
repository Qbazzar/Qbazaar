<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OfferResource\Pages;
use App\Models\Offer;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only listing of buyer offers — Sprint 9 lifecycle audit. No edits;
 * an admin can dispatch the existing offer-action endpoints when needed.
 */
class OfferResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 53;

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.offers');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.offer.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.offer.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.fields.id'))
                    ->limit(8),

                TextColumn::make('amount')
                    ->label(__('admin.fields.amount'))
                    ->money('QAR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge(),

                TextColumn::make('buyer_id')
                    ->label(__('admin.fields.buyer'))
                    ->limit(10),

                TextColumn::make('seller_id')
                    ->label(__('admin.fields.seller'))
                    ->limit(10),

                TextColumn::make('expires_at')
                    ->label(__('admin.fields.expires_at'))
                    ->dateTime()
                    ->since(),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'rejected' => 'Rejected',
                        'withdrawn' => 'Withdrawn',
                        'expired' => 'Expired',
                    ]),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffers::route('/'),
            'view' => Pages\ViewOffer::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
