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
use App\Http\Middleware\LocaleMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
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
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('QBazaar Admin')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->favicon('/brand/favicon.ico')
            ->colors([
                'primary' => Color::Orange,
                // Neutral (not Slate) so dark mode is true black-grey, no navy tint.
                'gray' => Color::Neutral,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->collapsedSidebarWidth('4.5rem')
            ->maxContentWidth(Width::Full)
            // Panel-side group order. Each label is the translated string so the
            // sidebar reads natively in Arabic / English. Resources declare their
            // group via getNavigationGroup() returning the SAME translation lookup,
            // so Filament's string match groups them under the right header.
            ->navigationGroups([
                NavigationGroup::make((string) __('admin.navigation_groups.marketplace')),
                NavigationGroup::make((string) __('admin.navigation_groups.communications')),
                NavigationGroup::make((string) __('admin.navigation_groups.moderation')),
                NavigationGroup::make((string) __('admin.navigation_groups.taxonomy')),
                NavigationGroup::make((string) __('admin.navigation_groups.content')),
                NavigationGroup::make((string) __('admin.navigation_groups.audit')),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Language switch in the user menu — shows only the OTHER language
            // so it reads as a one-tap toggle. Persists to the user's `language`
            // column; LocaleMiddleware applies it on the next request.
            ->userMenuItems([
                MenuItem::make()
                    ->label('العربية')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('admin.locale', ['locale' => 'ar']))
                    ->visible(fn (): bool => app()->getLocale() !== 'ar'),
                MenuItem::make()
                    ->label('English')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('admin.locale', ['locale' => 'en']))
                    ->visible(fn (): bool => app()->getLocale() !== 'en'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.admin.head')->render(),
            )
            // Brand mark in the topbar (single logo — the sidebar-header brand
            // is hidden in CSS to avoid the duplicate).
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.topbar-brand')->render(),
            )
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
                // Resolve the request locale from the staff member's `language`
                // column so both our admin.* strings AND Filament's own bundled
                // UI translations render in Arabic / English per preference.
                LocaleMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
