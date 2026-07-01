<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\PriceType;
use App\Events\Ads\AdApproved;
use App\Events\Ads\AdRejected;
use App\Filament\Admin\Resources\AdResource\Pages;
use App\Models\Ad;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Filament admin surface for ads.
 *
 * Lifecycle actions on the row mirror the public API:
 *  - Approve  → fires AdApproved + transitions PENDING → ACTIVE
 *  - Reject   → fires AdRejected with an admin_notes string captured as the
 *               ModerationResult flag list, transitions to REJECTED.
 *  - Feature  → toggles the boolean column straight on the row.
 *  - Force-expire / Force-delete are admin escape hatches; force-delete uses
 *    Filament's built-in ForceDeleteAction (soft-delete is the normal flow).
 *
 * The approve/reject actions are deliberately gated by direct status checks
 * rather than `canTransitionTo` — admins SHOULD be able to bypass the
 * lifecycle when something has gone wrong; the audit log records the move.
 */
class AdResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Ad::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.ads');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.ad.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.ad.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label(__('admin.fields.title'))
                        ->required()
                        ->maxLength(120)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label(__('admin.fields.description'))
                        ->required()
                        ->rows(6)
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.sections.taxonomy'))
                ->columns(2)
                ->schema([
                    Select::make('category_id')
                        ->label(__('admin.fields.category'))
                        ->relationship('category', 'slug')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('location_id')
                        ->label(__('admin.fields.location'))
                        ->relationship('location', 'slug')
                        ->searchable()
                        ->preload()
                        ->required(),
                ]),

            Section::make(__('admin.sections.pricing'))
                ->columns(3)
                ->schema([
                    TextInput::make('price')
                        ->label(__('admin.fields.price'))
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0),

                    Select::make('price_type')
                        ->label(__('admin.fields.price_type'))
                        ->options([
                            'fixed' => __('admin.price_type.fixed'),
                            'negotiable' => __('admin.price_type.negotiable'),
                            'free' => __('admin.price_type.free'),
                            'contact' => __('admin.price_type.contact'),
                        ])
                        ->required(),

                    Select::make('condition')
                        ->label(__('admin.fields.condition'))
                        ->options([
                            Condition::NEW->value => __('admin.condition.new'),
                            Condition::LIKE_NEW->value => __('admin.condition.like_new'),
                            Condition::USED->value => __('admin.condition.used'),
                        ]),
                ]),

            Section::make(__('admin.sections.moderation'))
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label(__('admin.fields.status'))
                        ->options(self::statusOptions())
                        ->required(),

                    Toggle::make('featured')
                        ->label(__('admin.fields.featured')),
                ]),
        ])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextEntry::make('title')
                        ->label(__('admin.fields.title'))
                        ->weight(FontWeight::SemiBold)
                        ->size(TextSize::Large)
                        ->columnSpanFull(),

                    TextEntry::make('description')
                        ->label(__('admin.fields.description'))
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('status')
                        ->label(__('admin.fields.status'))
                        ->badge()
                        ->formatStateUsing(static fn (AdStatus $state): string => $state->label()[app()->getLocale()] ?? $state->label()['en'])
                        ->color(static fn (AdStatus $state): string => match ($state) {
                            AdStatus::ACTIVE => 'success',
                            AdStatus::PENDING => 'warning',
                            AdStatus::REJECTED, AdStatus::BLOCKED => 'danger',
                            AdStatus::SOLD => 'info',
                            AdStatus::DRAFT, AdStatus::EXPIRED => 'gray',
                        }),

                    IconEntry::make('featured')
                        ->label(__('admin.fields.featured'))
                        ->boolean(),
                ]),

            Section::make(__('admin.sections.pricing'))
                ->columns(3)
                ->schema([
                    TextEntry::make('price')
                        ->label(__('admin.fields.price'))
                        ->money('QAR')
                        ->placeholder('—'),

                    TextEntry::make('price_type')
                        ->label(__('admin.fields.price_type'))
                        ->badge()
                        ->formatStateUsing(static fn (PriceType $state): string => (string) __('admin.price_type.' . $state->value))
                        ->color('gray'),

                    TextEntry::make('condition')
                        ->label(__('admin.fields.condition'))
                        ->badge()
                        ->placeholder('—')
                        ->formatStateUsing(static fn (?Condition $state): string => $state ? (string) __('admin.condition.' . $state->value) : '—')
                        ->color('gray'),
                ]),

            Section::make(__('admin.sections.taxonomy'))
                ->columns(3)
                ->schema([
                    TextEntry::make('user.full_name')
                        ->label(__('admin.fields.seller'))
                        ->placeholder('—'),

                    TextEntry::make('category.name')
                        ->label(__('admin.fields.category'))
                        ->badge()
                        ->color('primary')
                        ->state(static fn (Ad $record): ?string => $record->category?->getLocalizedName(app()->getLocale()))
                        ->placeholder('—'),

                    TextEntry::make('location.name')
                        ->label(__('admin.fields.location'))
                        ->badge()
                        ->color('info')
                        ->state(static fn (Ad $record): ?string => $record->location?->getLocalizedName(app()->getLocale()))
                        ->placeholder('—'),

                    TextEntry::make('acceptedOffer.buyer.full_name')
                        ->label(__('admin.fields.buyer'))
                        ->placeholder('—')
                        ->visible(static fn (Ad $record): bool => $record->status === AdStatus::SOLD),
                ]),

            Section::make(__('admin.sections.images'))
                ->collapsible()
                ->schema([
                    ImageEntry::make('media_thumbnails')
                        ->label('')
                        ->hiddenLabel()
                        ->placeholder('—')
                        ->state(static function (Ad $record): array {
                            return $record
                                ->getMedia('images')
                                ->map(static fn ($m) => $m->hasGeneratedConversion('medium') ? $m->getUrl('medium') : $m->getUrl())
                                ->all();
                        })
                        ->square()
                        ->size(120)
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.sections.audit'))
                ->collapsed()
                ->columns(3)
                ->schema([
                    TextEntry::make('views_count')
                        ->label(__('admin.fields.views_count'))
                        ->numeric(),

                    TextEntry::make('favorites_count')
                        ->label(__('admin.fields.favorites_count'))
                        ->numeric(),

                    TextEntry::make('id')
                        ->label(__('admin.fields.id'))
                        ->copyable()
                        ->fontFamily(FontFamily::Mono),

                    TextEntry::make('published_at')
                        ->label(__('admin.fields.published_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

                    TextEntry::make('expires_at')
                        ->label(__('admin.fields.expires_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
                        ->dateTime()
                        ->since(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(static function (Ad $record): ?string {
                        $first = $record->getFirstMedia('images');

                        return $first?->getUrl('thumbnail') ?: $first?->getUrl();
                    })
                    ->square()
                    ->size(48),

                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->searchable()
                    ->limit(40)
                    ->sortable(),

                TextColumn::make('user.full_name')
                    ->label(__('admin.fields.seller'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label(__('admin.fields.category'))
                    ->state(static fn (Ad $record): ?string => $record->category?->getLocalizedName(app()->getLocale()))
                    ->toggleable(),

                TextColumn::make('location.name')
                    ->label(__('admin.fields.location'))
                    ->state(static fn (Ad $record): ?string => $record->location?->getLocalizedName(app()->getLocale()))
                    ->toggleable(),

                TextColumn::make('price')
                    ->label(__('admin.fields.price'))
                    ->money('QAR', divideBy: 1)
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->formatStateUsing(static fn (AdStatus $state): string => $state->label()[app()->getLocale()] ?? $state->label()['en'])
                    ->color(static fn (AdStatus $state): string => match ($state) {
                        AdStatus::ACTIVE => 'success',
                        AdStatus::PENDING => 'warning',
                        AdStatus::REJECTED, AdStatus::BLOCKED => 'danger',
                        AdStatus::SOLD => 'info',
                        AdStatus::DRAFT, AdStatus::EXPIRED => 'gray',
                    }),

                IconColumn::make('featured')
                    ->label(__('admin.fields.featured'))
                    ->boolean(),

                TextColumn::make('views_count')
                    ->label(__('admin.fields.views_count'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('favorites_count')
                    ->label(__('admin.fields.favorites_count'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(self::statusOptions()),

                SelectFilter::make('category_id')
                    ->label(__('admin.fields.category'))
                    ->relationship('category', 'slug')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location_id')
                    ->label(__('admin.fields.location'))
                    ->relationship('location', 'slug')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('featured')
                    ->label(__('admin.fields.featured')),

                SelectFilter::make('condition')
                    ->label(__('admin.fields.condition'))
                    ->options([
                        Condition::NEW->value => 'New',
                        Condition::LIKE_NEW->value => 'Like new',
                        Condition::USED->value => 'Used',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('approve')
                    ->iconButton()
                    ->label(__('admin.actions.approve'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(static fn (Ad $record): bool => $record->status === AdStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(static fn (Ad $record) => self::approve($record)),

                Action::make('reject')
                    ->iconButton()
                    ->label(__('admin.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(static fn (Ad $record): bool => $record->status === AdStatus::PENDING)
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label(__('admin.fields.admin_notes'))
                            ->required()
                            ->rows(3),
                    ])
                    ->action(static fn (Ad $record, array $data) => self::reject($record, (string) ($data['admin_notes'] ?? ''))),

                Action::make('feature')
                    ->iconButton()
                    ->label(__('admin.actions.feature'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->action(static function (Ad $record): void {
                        $record->forceFill(['featured' => ! $record->featured])->save();
                        Notification::make()->title('Updated.')->success()->send();
                    }),

                Action::make('force_expire')
                    ->iconButton()
                    ->label(__('admin.actions.force_expire'))
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->visible(static fn (Ad $record): bool => $record->status === AdStatus::ACTIVE)
                    ->requiresConfirmation()
                    ->action(static function (Ad $record): void {
                        $record->markExpired();
                        Notification::make()->title('Ad expired.')->success()->send();
                    }),

                // Suspend a live ad (ACTIVE → BLOCKED) and pull it from search.
                Action::make('suspend')
                    ->iconButton()
                    ->label(__('admin.actions.suspend'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(static fn (Ad $record): bool => $record->status === AdStatus::ACTIVE)
                    ->requiresConfirmation()
                    ->action(static fn (Ad $record) => self::suspend($record)),

                // Lift a suspension (BLOCKED → ACTIVE) and re-index it.
                Action::make('unsuspend')
                    ->iconButton()
                    ->label(__('admin.actions.unsuspend'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(static fn (Ad $record): bool => $record->status === AdStatus::BLOCKED)
                    ->requiresConfirmation()
                    ->action(static fn (Ad $record) => self::unsuspend($record)),

                DeleteAction::make(),
                ForceDeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAds::route('/'),
            'view' => Pages\ViewAd::route('/{record}'),
            'edit' => Pages\EditAd::route('/{record}/edit'),
        ];
    }

    /**
     * Approve a pending ad: transitions to ACTIVE via the model's publish()
     * lifecycle and fires AdApproved so notification + search-index listeners
     * run. We use the same event the public API would, on purpose — admin
     * approvals must be indistinguishable downstream.
     */
    public static function approve(Ad $ad): void
    {
        $ad->publish();
        AdApproved::dispatch($ad);

        Notification::make()
            ->title(__('admin.actions.ad_approved'))
            ->success()
            ->send();
    }

    /** Suspend a live ad (ACTIVE → BLOCKED) and pull it from search. */
    public static function suspend(Ad $ad): void
    {
        $ad->forceFill(['status' => AdStatus::BLOCKED])->save();
        $ad->unsearchable();

        Notification::make()
            ->title(__('admin.actions.ad_suspended'))
            ->success()
            ->send();
    }

    /** Lift a suspension (BLOCKED → ACTIVE) and re-index it. */
    public static function unsuspend(Ad $ad): void
    {
        $ad->publish();
        AdApproved::dispatch($ad);

        Notification::make()
            ->title(__('admin.actions.ad_unsuspended'))
            ->success()
            ->send();
    }

    /**
     * Reject a pending ad and persist admin_notes inline on the ad payload
     * for later audit. We wrap the notes in a ModerationResult so the
     * AdRejected event keeps the existing shape — listeners care about the
     * `flags` list, not where it came from.
     */
    public static function reject(Ad $ad, string $notes): void
    {
        $ad->forceFill([
            'status' => AdStatus::REJECTED,
            'published_at' => null,
            'expires_at' => null,
        ])->save();

        $ad->unsearchable();

        $result = ModerationResult::rejected(['admin_manual'], ['admin_notes' => $notes]);
        AdRejected::dispatch($ad, $result);

        Notification::make()
            ->title(__('admin.actions.ad_rejected'))
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return [
            AdStatus::DRAFT->value => 'Draft',
            AdStatus::PENDING->value => 'Pending',
            AdStatus::ACTIVE->value => 'Active',
            AdStatus::SOLD->value => 'Sold',
            AdStatus::EXPIRED->value => 'Expired',
            AdStatus::REJECTED->value => 'Rejected',
            AdStatus::BLOCKED->value => 'Blocked',
        ];
    }
}
