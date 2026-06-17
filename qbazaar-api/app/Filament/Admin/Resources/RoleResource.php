<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RoleResource\Pages;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Staff role + permission editor.
 *
 * Roles are the backbone of the admin RBAC, so the whole resource is gated to
 * `super_admin` only — a moderator or support agent must never be able to
 * grant themselves (or anyone else) elevated permissions. The gate is enforced
 * in canAccess() so Filament hides the navigation entry AND blocks the routes.
 */
class RoleResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 70;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.audit');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.roles');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.role.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.role.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin.fields.name'))
                        ->required()
                        ->maxLength(125)
                        ->unique(ignoreRecord: true),

                    // Filament authenticates against the web guard; keep the
                    // value consistent so Spatie resolves the role correctly.
                    Hidden::make('guard_name')
                        ->default('web'),
                ]),

            Section::make(__('admin.sections.permissions'))
                ->schema([
                    CheckboxList::make('permissions')
                        ->label(__('admin.fields.permissions'))
                        ->relationship('permissions', 'name')
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(3),
                ]),
        ])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextEntry::make('name')
                        ->label(__('admin.fields.name'))
                        ->weight(FontWeight::SemiBold)
                        ->badge()
                        ->color('primary'),

                    TextEntry::make('guard_name')
                        ->label(__('admin.fields.guard_name'))
                        ->badge()
                        ->color('gray'),
                ]),

            Section::make(__('admin.sections.permissions'))
                ->schema([
                    TextEntry::make('permissions.name')
                        ->label(__('admin.fields.permissions'))
                        ->badge()
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query
                ->withCount(['permissions', 'users']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('permissions_count')
                    ->label(__('admin.fields.permissions'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label(__('admin.fields.users'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
            ])
            ->defaultSort('name');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
