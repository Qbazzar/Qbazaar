<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\OfferStatus;
use App\Filament\Admin\Resources\OfferResource\Pages;
use App\Models\Offer;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.communications');
    }

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(3)
                ->schema([
                    TextEntry::make('id')
                        ->label(__('admin.fields.id'))
                        ->copyable()
                        ->fontFamily(FontFamily::Mono),

                    TextEntry::make('amount')
                        ->label(__('admin.fields.amount'))
                        ->money(static fn (Offer $record): string => $record->currency ?: 'QAR')
                        ->weight(FontWeight::SemiBold),

                    TextEntry::make('status')
                        ->label(__('admin.fields.status'))
                        ->badge()
                        ->color(static fn (OfferStatus $state): string => match ($state) {
                            OfferStatus::PENDING => 'warning',
                            OfferStatus::ACCEPTED => 'success',
                            OfferStatus::REJECTED, OfferStatus::WITHDRAWN, OfferStatus::EXPIRED => 'gray',
                        }),

                    TextEntry::make('note')
                        ->label(__('admin.fields.body'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.sections.target'))
                ->columns(3)
                ->schema([
                    TextEntry::make('ad.title')
                        ->label(__('admin.fields.ad'))
                        ->placeholder('—'),

                    TextEntry::make('buyer.full_name')
                        ->label(__('admin.fields.buyer'))
                        ->placeholder('—'),

                    TextEntry::make('seller.full_name')
                        ->label(__('admin.fields.seller'))
                        ->placeholder('—'),
                ]),

            Section::make(__('admin.sections.audit'))
                ->collapsed()
                ->columns(3)
                ->schema([
                    TextEntry::make('expires_at')
                        ->label(__('admin.fields.expires_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

                    TextEntry::make('accepted_at')
                        ->label(__('admin.fields.accepted_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

                    TextEntry::make('rejected_at')
                        ->label(__('admin.fields.rejected_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

                    TextEntry::make('withdrawn_at')
                        ->label(__('admin.fields.withdrawn_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
                        ->dateTime()
                        ->since(),

                    TextEntry::make('updated_at')
                        ->label(__('admin.fields.updated_at'))
                        ->dateTime()
                        ->since(),
                ]),
        ]);
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
