<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\BlockPolicy;
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

        Gate::define('block-user', [BlockPolicy::class, 'block']);
        Gate::define('unblock-user', [BlockPolicy::class, 'unblock']);
    }
}
