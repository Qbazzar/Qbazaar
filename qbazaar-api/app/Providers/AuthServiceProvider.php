<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\AdPolicy;
use App\Policies\BlockPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\OfferPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires our policies into the authorization Gate.
 *
 * We register against `User` for the account-management abilities, and ship
 * a dedicated `block-user` / `unblock-user` gate that matches the BlockPolicy.
 * Both register here so a single grep tells you "where do these gates come
 * from".
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, AccountPolicy::class);
        Gate::policy(Ad::class, AdPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);

        Gate::define('block-user', [BlockPolicy::class, 'block']);
        Gate::define('unblock-user', [BlockPolicy::class, 'unblock']);

        // Custom Ad-related abilities — these live outside the policy's
        // CRUD set so controllers can authorize them by string name
        // (`$this->authorize('manage-images', $ad)`) while keeping the
        // policy class as the single source of the rule.
        Gate::define('manage-images', [AdPolicy::class, 'manageImages']);
        Gate::define('mark-sold', [AdPolicy::class, 'markSold']);
        Gate::define('renew', [AdPolicy::class, 'renew']);
    }
}
