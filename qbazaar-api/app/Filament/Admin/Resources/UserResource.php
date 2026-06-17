<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\UserStatus;
use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Password;

/**
 * Filament resource for the public user base.
 *
 * Security model:
 *  - View / Edit gated by `users.view` / `users.update`.
 *  - Ban toggle gated by `users.ban`. We deliberately do NOT ship an
 *    impersonate action this sprint; the value-for-effort ratio of a
 *    safe impersonate (audit trail, session pinning) is high enough that
 *    we'll do it properly in Sprint 12. The action is replaced by a
 *    "view public profile" deep link.
 *  - Destroy gated by `users.delete`. Soft-delete only — the existing
 *    `deletion_requested_at` flow drives final purge.
 */
class UserResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.users');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.user.plural');
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Form — edit-only-the-important-fields. We deliberately don't
     *  expose `password` here; resetting goes through the reset action
     *  which uses the password broker (so the user gets a normal flow).
     * ──────────────────────────────────────────────────────────────────*/
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextInput::make('full_name')
                        ->label(__('admin.fields.full_name'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label(__('admin.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label(__('admin.fields.phone'))
                        ->tel()
                        ->required()
                        ->maxLength(32),

                    Select::make('language')
                        ->label(__('admin.fields.language'))
                        ->options(self::enumOptions(Language::class))
                        ->required(),
                ]),

            Section::make(__('admin.sections.moderation'))
                ->columns(2)
                ->schema([
                    Select::make('account_type')
                        ->label(__('admin.fields.account_type'))
                        ->options(self::enumOptions(AccountType::class))
                        ->required(),

                    Select::make('status')
                        ->label(__('admin.fields.status'))
                        ->options(self::enumOptions(UserStatus::class))
                        ->required(),
                ]),

            Section::make(__('admin.sections.verification'))
                ->columns(2)
                ->schema([
                    Toggle::make('email_verified')->label(__('admin.fields.email_verified')),
                    Toggle::make('phone_verified')->label(__('admin.fields.phone_verified')),
                ]),

            // Role assignment is how an admin promotes a user to staff
            // (admin / moderator / support). Restricted to super_admin so
            // lower-privilege staff cannot escalate anyone's access.
            Section::make(__('admin.sections.roles'))
                ->visible(static fn (): bool => auth()->user()?->hasRole('super_admin') === true)
                ->schema([
                    Select::make('roles')
                        ->label(__('admin.fields.roles'))
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload(),
                ]),

            Section::make(__('admin.sections.meta'))
                ->collapsed()
                ->schema([
                    Textarea::make('privacy_settings')
                        ->label(__('admin.fields.privacy_settings'))
                        ->disabled()
                        ->rows(4)
                        ->dehydrated(false)
                        ->formatStateUsing(
                            static fn (mixed $state): string => is_string($state)
                            ? $state
                            : (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                        ),
                ]),
        ])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(3)
                ->schema([
                    ImageEntry::make('avatar_url')
                        ->label(__('admin.fields.avatar'))
                        ->getStateUsing(static fn (User $record): ?string => $record->avatarThumbUrl())
                        ->circular()
                        ->placeholder('—'),

                    TextEntry::make('full_name')
                        ->label(__('admin.fields.full_name'))
                        ->weight(FontWeight::SemiBold)
                        ->placeholder('—'),

                    TextEntry::make('id')
                        ->label(__('admin.fields.id'))
                        ->copyable()
                        ->fontFamily(FontFamily::Mono),

                    TextEntry::make('email')
                        ->label(__('admin.fields.email'))
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('phone')
                        ->label(__('admin.fields.phone'))
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('language')
                        ->label(__('admin.fields.language'))
                        ->badge()
                        ->color('gray'),
                ]),

            Section::make(__('admin.sections.moderation'))
                ->columns(2)
                ->schema([
                    TextEntry::make('account_type')
                        ->label(__('admin.fields.account_type'))
                        ->badge()
                        ->color('info'),

                    TextEntry::make('status')
                        ->label(__('admin.fields.status'))
                        ->badge()
                        ->color(static fn (UserStatus $state): string => match ($state) {
                            UserStatus::ACTIVE => 'success',
                            UserStatus::SUSPENDED => 'danger',
                            UserStatus::DEACTIVATED, UserStatus::PENDING_DELETION => 'gray',
                        }),
                ]),

            Section::make(__('admin.sections.verification'))
                ->columns(2)
                ->schema([
                    IconEntry::make('email_verified')
                        ->label(__('admin.fields.email_verified'))
                        ->boolean(),

                    IconEntry::make('phone_verified')
                        ->label(__('admin.fields.phone_verified'))
                        ->boolean(),
                ]),

            Section::make(__('admin.sections.roles'))
                ->schema([
                    TextEntry::make('roles.name')
                        ->label(__('admin.fields.roles'))
                        ->badge()
                        ->color('primary')
                        ->placeholder('—'),
                ]),

            Section::make(__('admin.sections.audit'))
                ->collapsed()
                ->columns(3)
                ->schema([
                    TextEntry::make('last_login_at')
                        ->label(__('admin.fields.last_login_at'))
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
                ImageColumn::make('avatar')
                    ->label(__('admin.fields.avatar'))
                    ->getStateUsing(static fn (User $record): ?string => $record->avatarThumbUrl())
                    ->circular()
                    ->size(40),

                TextColumn::make('full_name')
                    ->label(__('admin.fields.full_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('admin.fields.email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->label(__('admin.fields.phone'))
                    ->searchable(),

                TextColumn::make('account_type')
                    ->label(__('admin.fields.account_type'))
                    ->badge(),

                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->color(static fn (UserStatus $state): string => match ($state) {
                        UserStatus::ACTIVE => 'success',
                        UserStatus::SUSPENDED => 'danger',
                        UserStatus::DEACTIVATED, UserStatus::PENDING_DELETION => 'gray',
                    }),

                IconColumn::make('email_verified')
                    ->label(__('admin.fields.email_verified'))
                    ->boolean(),

                TextColumn::make('last_login_at')
                    ->label(__('admin.fields.last_login_at'))
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(self::enumOptions(UserStatus::class)),

                SelectFilter::make('account_type')
                    ->label(__('admin.fields.account_type'))
                    ->options(self::enumOptions(AccountType::class)),

                TernaryFilter::make('email_verified')
                    ->label(__('admin.fields.email_verified')),

                TernaryFilter::make('phone_verified')
                    ->label(__('admin.fields.phone_verified')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('reset_password')
                    ->iconButton()
                    ->label(__('admin.actions.reset_password'))
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(static function (User $record): void {
                        Password::broker()->sendResetLink(['email' => $record->email]);
                        Notification::make()
                            ->title(__('admin.actions.reset_password_sent'))
                            ->success()
                            ->send();
                    }),
                Action::make('ban')
                    ->iconButton()
                    ->label(static fn (User $record): string => (string) __(
                        $record->status === UserStatus::SUSPENDED ? 'admin.actions.unban' : 'admin.actions.ban',
                    ))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(static function (User $record): void {
                        $isSuspended = $record->status === UserStatus::SUSPENDED;
                        $record->forceFill([
                            'status' => $isSuspended ? UserStatus::ACTIVE : UserStatus::SUSPENDED,
                        ])->save();

                        Notification::make()
                            ->title(__($isSuspended ? 'admin.actions.unban_applied' : 'admin.actions.ban_applied'))
                            ->success()
                            ->send();
                    }),
                Action::make('view_as_user')
                    ->iconButton()
                    ->label(__('admin.actions.view_as_user'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(static fn (User $record): string => rtrim((string) config('qbazaar.web_url', config('app.url')), '/')
                        . '/users/' . $record->id, shouldOpenInNewTab: true),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_ban')
                        ->label(__('admin.actions.ban'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(static function (Collection $records): void {
                            /** @var Collection<int, User> $records */
                            $records->each(static fn (User $u) => $u->forceFill(['status' => UserStatus::SUSPENDED])->save());
                            Notification::make()->title(__('admin.actions.ban_applied'))->success()->send();
                        }),
                    BulkAction::make('bulk_suspend_release')
                        ->label(__('admin.actions.unban'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(static function (Collection $records): void {
                            /** @var Collection<int, User> $records */
                            $records->each(static fn (User $u) => $u->forceFill(['status' => UserStatus::ACTIVE])->save());
                            Notification::make()->title(__('admin.actions.unban_applied'))->success()->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Map a backed enum to a [value => Title-cased label] options array.
     *
     * @param class-string<BackedEnum> $enum
     * @return array<string, string>
     */
    private static function enumOptions(string $enum): array
    {
        $options = [];
        foreach ($enum::cases() as $case) {
            /** @var BackedEnum $case */
            $value = (string) $case->value;
            $options[$value] = (string) str($value)->replace('_', ' ')->title();
        }

        return $options;
    }
}
