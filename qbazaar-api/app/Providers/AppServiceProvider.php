<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Observers\AdListingCacheObserver;
use App\Observers\AdObserver;
use App\Observers\AdOffersObserver;
use App\Observers\TaxonomyCacheObserver;
use App\Observers\UserObserver;
use App\Services\Moderation\ModerationRulesService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        Ad::observe([AdObserver::class, AdOffersObserver::class, AdListingCacheObserver::class]);
        Category::observe(TaxonomyCacheObserver::class);
        Location::observe(TaxonomyCacheObserver::class);

        Model::preventLazyLoading(! $this->app->isProduction());

        // Rate limiters MUST be registered here (not in the withRouting `then:`
        // closure) so they survive route:cache — Laravel skips that closure when
        // routes come from cache, and the throttle middleware would crash with
        // "Rate limiter [api] is not defined" in production.
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        // Every send costs an SMS: cap each phone and each IP per day so
        // rotating either one alone cannot pump messages.
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute((int) config('qbazaar.otp.max_per_minute'))->by('otp:' . $r->ip() . '|' . self::phoneKey($r)),
            Limit::perDay((int) config('qbazaar.otp.max_per_day_per_phone'))->by('otp-phone:' . self::phoneKey($r)),
            Limit::perDay((int) config('qbazaar.otp.max_per_day_per_ip'))->by('otp-ip:' . $r->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute((int) config('qbazaar.otp.verify_max_per_minute'))->by('otp-verify:' . $r->ip() . '|' . self::phoneKey($r)));
        RateLimiter::for('conversations', fn (Request $r) => [
            Limit::perMinute((int) config('qbazaar.messaging.new_conversations_per_minute'))->by('conversations:' . (optional($r->user())->id ?: $r->ip())),
            Limit::perDay((int) config('qbazaar.messaging.new_conversations_per_day'))->by('conversations-day:' . (optional($r->user())->id ?: $r->ip())),
        ]);
        RateLimiter::for('offers', fn (Request $r) => [
            Limit::perMinute((int) config('qbazaar.offers.max_per_minute'))->by('offers:' . (optional($r->user())->id ?: $r->ip())),
            Limit::perDay((int) config('qbazaar.offers.max_per_day'))->by('offers-day:' . (optional($r->user())->id ?: $r->ip())),
        ]);
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(60)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('publish', fn (Request $r) => Limit::perMinute((int) config('qbazaar.ads.publish_attempts_per_minute_per_user'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('drafts', fn (Request $r) => Limit::perHour((int) config('qbazaar.ads.drafts_per_hour_per_user'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('messages', fn (Request $r) => Limit::perMinute((int) config('qbazaar.messaging.rate_limit_per_minute'))->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));
        // Each hit sends an email or SMS.
        RateLimiter::for('contact-change', fn (Request $r) => Limit::perHour((int) config('qbazaar.account.contact_change_attempts_per_hour'))
            ->by('contact-change:' . (optional($r->user())->id ?: $r->ip())));
    }

    /**
     * Limiters run before validation, so a non-string phone must still yield
     * a key instead of an array-to-string error.
     */
    private static function phoneKey(Request $request): string
    {
        $phone = $request->input('phone');

        return is_string($phone) ? $phone : '';
    }
}
