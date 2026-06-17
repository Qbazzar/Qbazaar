<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Ads\AdApproved;
use App\Events\Ads\AdExpired;
use App\Events\Ads\AdExpiringSoon;
use App\Events\Ads\AdPublished;
use App\Events\Ads\AdRejected;
use App\Events\Ads\AdRenewed;
use App\Listeners\Ads\IndexAdInSearch;
use App\Listeners\Ads\RemoveAdFromSearch;
use App\Listeners\Ads\SendAdNotifications;
use App\Listeners\Notifications\BroadcastDatabaseNotificationCreated;
use App\Listeners\Notifications\PruneStaleDeviceTokens;
use App\Models\Ad;
use App\Models\User;
use App\Observers\AdObserver;
use App\Observers\UserObserver;
use App\Services\Moderation\ModerationRulesService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The moderation rule list is parsed once on construction; binding as
        // a singleton avoids re-parsing the banned-words array on every
        // publish call within a single worker process.
        $this->app->singleton(ModerationRulesService::class);

        // Telescope is installed as a dev dependency, so its classes only
        // exist when composer ran without --no-dev. Guard the registration so
        // production deploys (composer install --no-dev) don't blow up.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        Ad::observe(AdObserver::class);

        // Admin tables show row actions as icon-only buttons with the label
        // surfaced on hover (Filament renders the label as the button tooltip).
        // Configuring the three built-in record actions globally keeps every
        // resource table consistent without repeating ->iconButton() per table;
        // custom row actions opt in individually at their call site.
        ViewAction::configureUsing(static fn (ViewAction $action): ViewAction => $action->iconButton());
        EditAction::configureUsing(static fn (EditAction $action): EditAction => $action->iconButton());
        DeleteAction::configureUsing(static fn (DeleteAction $action): DeleteAction => $action->iconButton());
        ForceDeleteAction::configureUsing(static fn (ForceDeleteAction $action): ForceDeleteAction => $action->iconButton());
        RestoreAction::configureUsing(static fn (RestoreAction $action): RestoreAction => $action->iconButton());

        // Rate limiters MUST be registered here (not in the withRouting `then:`
        // closure) so they survive route:cache — Laravel skips that closure when
        // routes come from cache, and the throttle middleware would crash with
        // "Rate limiter [api] is not defined" in production.
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('otp', fn (Request $r) => Limit::perMinute(3)->by($r->input('phone') ?? $r->ip()));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(60)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('publish', fn (Request $r) => Limit::perDay((int) config('qbazaar.ads.daily_publish_limit_per_user'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('messages', fn (Request $r) => Limit::perMinute((int) config('qbazaar.messaging.rate_limit_per_minute'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));

        // Laravel 12 prefers event discovery, but explicit listener bindings
        // keep the routing readable and survive `package:discover` cache
        // invalidation. The two-way fan-out (index/search + notifications)
        // is concentrated here so future events plug in by appending lines.
        Event::listen(AdPublished::class, [IndexAdInSearch::class, 'handle']);
        Event::listen(AdApproved::class, [IndexAdInSearch::class, 'handle']);

        Event::listen(AdRejected::class, [RemoveAdFromSearch::class, 'handle']);
        Event::listen(AdExpired::class, [RemoveAdFromSearch::class, 'handle']);

        Event::listen(AdPublished::class, [SendAdNotifications::class, 'handle']);
        Event::listen(AdApproved::class, [SendAdNotifications::class, 'handle']);
        Event::listen(AdRejected::class, [SendAdNotifications::class, 'handle']);
        Event::listen(AdExpiringSoon::class, [SendAdNotifications::class, 'handle']);
        Event::listen(AdExpired::class, [SendAdNotifications::class, 'handle']);
        Event::listen(AdRenewed::class, [SendAdNotifications::class, 'handle']);

        // Bridges Laravel's NotificationSent -> our own NotificationCreated
        // broadcast (database channel only). See the listener for details.
        Event::listen(NotificationSent::class, [BroadcastDatabaseNotificationCreated::class, 'handle']);

        // FCM reports dead registration tokens via NotificationFailed —
        // prune them so future pushes stop fanning out to gone devices.
        Event::listen(NotificationFailed::class, [PruneStaleDeviceTokens::class, 'handle']);
    }
}
