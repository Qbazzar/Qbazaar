<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Custom dashboard with a "Send announcement" header action.
 *
 * The action is gated by Spatie Permission's `notifications.broadcast`.
 * We compose the target audience from a fixed three-way choice — `all_users`,
 * `active_users`, `users_with_active_ads`. Anything more granular belongs in
 * the user resource's bulk actions, not here.
 *
 * We chunk the send via `notify()` per slice rather than calling
 * `Notification::send()` on the entire collection at once because:
 *  - The notifiable list can grow to tens of thousands; loading it as a single
 *    collection burns memory.
 *  - Each notification is queued (ShouldQueue) so the actual delivery happens
 *    out-of-band; we only need to enqueue.
 */
class Dashboard extends BaseDashboard
{
    /** Chunk size for fan-out — tuned for keeping a single DB transaction short. */
    private const int BROADCAST_CHUNK = 500;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_announcement')
                ->label(__('admin.actions.send_announcement'))
                ->icon('heroicon-o-megaphone')
                ->color('primary')
                ->visible(static function (): bool {
                    $user = Auth::user();

                    return $user !== null
                        && method_exists($user, 'can')
                        && $user->can('notifications.broadcast');
                })
                ->schema([
                    TextInput::make('title')
                        ->label(__('admin.announcement.title_field'))
                        ->required()
                        ->maxLength(120),

                    Textarea::make('body')
                        ->label(__('admin.announcement.body_field'))
                        ->required()
                        ->rows(5)
                        ->maxLength(2000),

                    Select::make('target')
                        ->label(__('admin.announcement.target_field'))
                        ->required()
                        ->default('all_users')
                        ->options([
                            'all_users' => __('admin.announcement.target.all_users'),
                            'active_users' => __('admin.announcement.target.active_users'),
                            'users_with_active_ads' => __('admin.announcement.target.users_with_active_ads'),
                        ]),
                ])
                ->action(function (array $data): void {
                    $count = $this->broadcast(
                        (string) ($data['title'] ?? ''),
                        (string) ($data['body'] ?? ''),
                        (string) ($data['target'] ?? 'all_users'),
                    );

                    Notification::make()
                        ->title((string) __('admin.actions.announcement_sent', ['count' => $count]))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Fan out a SystemAnnouncementNotification to the chosen audience and
     * return the recipient count for the toast.
     */
    private function broadcast(string $title, string $body, string $target): int
    {
        $query = match ($target) {
            'active_users' => User::query()->where('status', UserStatus::ACTIVE->value),
            'users_with_active_ads' => User::query()->whereIn(
                'id',
                Ad::query()->where('status', AdStatus::ACTIVE->value)->select('user_id'),
            ),
            default => User::query(),
        };

        $count = 0;
        $notification = new SystemAnnouncementNotification($title, $body);

        $query
            ->select(['id', 'email', 'language'])
            ->chunkById(self::BROADCAST_CHUNK, function ($users) use ($notification, &$count): void {
                NotificationFacade::send($users, $notification);
                $count += $users->count();
            });

        return $count;
    }
}
