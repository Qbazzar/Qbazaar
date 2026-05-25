<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\AdsPublishedChart;
use App\Filament\Admin\Widgets\AdsStatsWidget;
use App\Filament\Admin\Widgets\RecentReportsWidget;
use App\Filament\Admin\Widgets\ReportsStatsWidget;
use App\Filament\Admin\Widgets\RevenueStatsWidget;
use App\Filament\Admin\Widgets\UsersStatsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Admin panel registration (Sprint 11).
 *
 * Why a single panel? QBazaar has exactly one staff surface — moderators,
 * support, and admins all collaborate from the same UI, gated by Spatie
 * Permission roles. Splitting into multiple panels would just multiply the
 * Filament boilerplate without buying us anything.
 *
 * Brand decisions:
 *   - `Color::Orange` is Filament's closest preset to the Coral #F37335
 *     primary used on the public app — keeps the staff UI visually consistent
 *     without forcing us to ship a custom theme yet.
 *   - `sidebarCollapsibleOnDesktop()` over `topNavigation()` because the
 *     admin nav will quickly exceed what fits horizontally once Sprint 12's
 *     CMS resources land.
 *   - `databaseNotifications()` surfaces the same notifications table the
 *     public app writes to, so admins see broadcast announcements + system
 *     events without a parallel feed.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('QBazaar Admin')
            ->brandLogo('/brand/logo.png')
            ->favicon('/brand/favicon.ico')
            ->colors([
                'primary' => Color::Orange,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                UsersStatsWidget::class,
                AdsStatsWidget::class,
                ReportsStatsWidget::class,
                RevenueStatsWidget::class,
                AdsPublishedChart::class,
                RecentReportsWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
