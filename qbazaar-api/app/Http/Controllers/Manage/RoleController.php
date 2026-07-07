<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when(
                $search !== '',
                fn ($query) => $query->where('name', 'like', "%{$search}%"),
            )
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'search' => $search,
        ]);
    }
}
