<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Ad;
use App\Models\User;
use App\Observers\AdObserver;
use App\Observers\UserObserver;
use App\Services\Moderation\ModerationRulesService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        // Rate limiters MUST be registered here (not in the withRouting `then:`
        // closure) so they survive route:cache — Laravel skips that closure when
        // routes come from cache, and the throttle middleware would crash with
        // "Rate limiter [api] is not defined" in production.
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('otp', fn (Request $r) => Limit::perMinute(3)->by($r->input('phone') ?? $r->ip()));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(60)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('publish', fn (Request $r) => Limit::perMinute((int) config('qbazaar.ads.publish_attempts_per_minute_per_user'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('drafts', fn (Request $r) => Limit::perHour((int) config('qbazaar.ads.drafts_per_hour_per_user'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('messages', fn (Request $r) => Limit::perMinute((int) config('qbazaar.messaging.rate_limit_per_minute'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));
    }
}
