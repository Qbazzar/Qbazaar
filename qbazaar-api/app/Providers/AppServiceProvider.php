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
use App\Models\Ad;
use App\Models\User;
use App\Observers\AdObserver;
use App\Observers\UserObserver;
use App\Services\Moderation\ModerationRulesService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        Ad::observe(AdObserver::class);

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
    }
}
