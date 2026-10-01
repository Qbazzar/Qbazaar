<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * The caller of a public route. Those routes run on the default guard, so
     * a Bearer token is only recognised through the sanctum guard explicitly.
     */
    protected function viewer(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user instanceof User ? $user : null;
    }
}
