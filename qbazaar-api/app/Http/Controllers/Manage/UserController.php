<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Admin\ImpersonateUserAction;
use App\Actions\Admin\SyncUserRolesAction;
use App\Actions\Users\SuspendUserAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\ImpersonateUserRequest;
use App\Http\Requests\Manage\UpdateUserRolesRequest;
use App\Models\User;
use App\Services\Admin\StaffHierarchy;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly StaffHierarchy $hierarchy) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $role = $request->string('role')->toString();
        $search = $request->string('q')->toString();

        $users = User::query()
            ->with('roles')
            ->when(
                in_array($status, array_column(UserStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $role !== '',
                fn ($query) => $query->whereHas('roles', fn ($inner) => $inner->where('name', $role)),
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'status' => $status,
            'role' => $role,
            'search' => $search,
            'statuses' => UserStatus::cases(),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function show(#[CurrentUser] User $actor, User $user): View
    {
        $user->load('roles');
        $user->loadCount('ads');

        $canManageUser = $this->hierarchy->canManage($actor, $user);

        return view('admin.users.show', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'canManageUser' => $canManageUser,
            'canManageRoles' => $canManageUser && $actor->can('roles.manage'),
        ]);
    }

    public function suspend(#[CurrentUser] User $actor, User $user, SuspendUserAction $suspendUser): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($actor, $user);

        $suspendUser->execute($user);

        return back()->with('status', 'تم إيقاف المستخدم.');
    }

    public function activate(#[CurrentUser] User $actor, User $user): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($actor, $user);

        $user->forceFill(['status' => UserStatus::ACTIVE])->save();

        return back()->with('status', 'تم تفعيل المستخدم.');
    }

    /** Email the user a password-reset link (self-service recovery on their behalf). */
    public function sendPasswordReset(#[CurrentUser] User $actor, User $user): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($actor, $user);

        Password::broker()->sendResetLink(['email' => $user->email]);

        return back()->with('status', 'تم إرسال رابط إعادة تعيين كلمة المرور للمستخدم.');
    }

    /**
     * Hands the web app a short-lived access token in the URL fragment, which
     * is never sent to servers or written to logs; the web /impersonate page
     * consumes and clears it. Staff accounts can't be impersonated to avoid
     * privilege confusion.
     */
    public function impersonate(
        ImpersonateUserRequest $request,
        #[CurrentUser]
        User $actor,
        User $user,
        ImpersonateUserAction $impersonateUser,
    ): RedirectResponse {
        if ($user->isStaff()) {
            return back()->with('error', __('admin.impersonation.staff_refused'));
        }

        $token = $impersonateUser->execute($actor, $user, (string) $request->validated('reason'), $request->ip());

        $webUrl = rtrim((string) config('qbazaar.web_url', config('app.url')), '/');
        $fragment = http_build_query([
            'access' => $token->plainTextToken,
            'expires_in' => (int) config('qbazaar.admin.impersonation_ttl_minutes') * 60,
            'name' => $user->full_name,
        ]);

        return redirect()->away("{$webUrl}/impersonate#{$fragment}");
    }

    public function updateRoles(
        UpdateUserRolesRequest $request,
        #[CurrentUser]
        User $actor,
        User $user,
        SyncUserRolesAction $syncRoles,
    ): RedirectResponse {
        $syncRoles->execute($actor, $user, $request->roles());

        return back()->with('status', 'تم تحديث أدوار المستخدم.');
    }
}
