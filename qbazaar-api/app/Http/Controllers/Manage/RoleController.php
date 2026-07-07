<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', ['roles' => $roles]);
    }
}
