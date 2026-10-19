<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Roles\UpdateRolePermissionsRequest;
use App\Models\User;
use App\Services\Admin\Roles\PermissionMatrix;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The role × permission matrix (AD-17.15), super_admin only.
 */
class RolePermissionController extends Controller
{
    public function edit(PermissionMatrix $matrix): View
    {
        $roles = $matrix->editableRoles();

        return view('admin.roles.permissions', [
            'roles' => $roles,
            'rows' => $matrix->rows($roles),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, #[CurrentUser] User $actor, PermissionMatrix $matrix): RedirectResponse
    {
        $matrix->update($actor, $request->matrix());

        return redirect()->route('admin.roles.permissions.edit')->with('status', __('admin.permission_matrix.flash.saved'));
    }
}
