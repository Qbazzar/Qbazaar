<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdResource\Pages;

use App\Enums\AdStatus;
use App\Filament\Admin\Resources\AdResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewAd extends ViewRecord
{
    protected static string $resource = AdResource::class;

    /**
     * Same moderation actions as the table row, surfaced on the detail page so
     * a reviewer can approve/publish, reject, or suspend without going back.
     *
     * @return array<int, Action|EditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('admin.actions.approve'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === AdStatus::PENDING)
                ->requiresConfirmation()
                ->action(function (): void {
                    AdResource::approve($this->getRecord());
                }),

            Action::make('reject')
                ->label(__('admin.actions.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->status === AdStatus::PENDING)
                ->schema([
                    Textarea::make('admin_notes')
                        ->label(__('admin.fields.admin_notes'))
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    AdResource::reject($this->getRecord(), (string) ($data['admin_notes'] ?? ''));
                }),

            Action::make('suspend')
                ->label(__('admin.actions.suspend'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->status === AdStatus::ACTIVE)
                ->requiresConfirmation()
                ->action(function (): void {
                    AdResource::suspend($this->getRecord());
                }),

            Action::make('unsuspend')
                ->label(__('admin.actions.unsuspend'))
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === AdStatus::BLOCKED)
                ->requiresConfirmation()
                ->action(function (): void {
                    AdResource::unsuspend($this->getRecord());
                }),

            EditAction::make(),
        ];
    }
}
