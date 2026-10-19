<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Admin\ImpersonateUserAction;
use App\Actions\Admin\SyncUserRolesAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\ImpersonateUserRequest;
use App\Http\Requests\Manage\Moderation\SanctionRequest;
use App\Http\Requests\Manage\UpdateUserRolesRequest;
use App\Models\User;
use App\Services\Admin\EntityTimeline;
use App\Services\Admin\StaffHierarchy;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use App\Services\Admin\UserDetailTabs;
use App\Services\Moderation\SanctionService;
use App\Services\Users\UserModerationService;
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
            ->with(['roles', 'avatarMedia'])
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

    public function show(
        Request $request,
        #[CurrentUser]
        User $actor,
        User $user,
        UserDetailTabs $tabs,
        EntityTimeline $timeline,
        StaffTwoFactorService $twoFactor,
        SanctionService $sanctions,
    ): View {
        $user->load(['roles', 'avatarMedia']);
        $user->loadCount('ads');

        $canManageUser = $this->hierarchy->canManage($actor, $user);
        $canManageRoles = $canManageUser && $actor->can('roles.manage');
        $activeTab = $tabs->resolve($actor, $request->string('tab')->toString());

        return view('admin.users.show', [
            'user' => $user,
            'roles' => $canManageRoles ? Role::orderBy('name')->get() : collect(),
            'canManageUser' => $canManageUser,
            'canManageRoles' => $canManageRoles,
            // Only staff have a two-factor credential, and only a senior colleague may clear it.
            'staffTwoFactorEnabled' => $canManageRoles && $user->isStaff() ? $twoFactor->isEnabled($user) : null,
            'tabs' => $tabs->visibleTo($actor),
            'activeTab' => $activeTab,
            'panel' => $tabs->panel($activeTab, $user),
            'panelView' => $tabs->view($activeTab),
            // The audit trail names staff members, so it follows the activity log's own permission.
            'timeline' => $actor->can('activity.view') ? rescue(fn () => $timeline->forSubject($user)) : null,
            // Why a suspended account is suspended; read only when it is.
            'sanction' => $user->status === UserStatus::SUSPENDED ? rescue(fn () => $sanctions->latestFor($user)) : null,
        ]);
    }

    /** One tab panel of the user page, fetched the first time its tab is opened. */
    public function tab(#[CurrentUser] User $actor, User $user, string $tab, UserDetailTabs $tabs): View
    {
        abort_unless(in_array($tab, $tabs->visibleTo($actor), true), 403);

        return view($tabs->view($tab), ['user' => $user, ...$tabs->panel($tab, $user)]);
    }

    public function suspend(SanctionRequest $request, #[CurrentUser] User $actor, User $user, SanctionService $sanctions): RedirectResponse
    {
        $sanctions->suspendUser($actor, $user, $request->sanction());

        return back()->with('status', __('admin.moderation.flash.user_suspended'));
    }

    public function activate(#[CurrentUser] User $actor, User $user, UserModerationService $moderation): RedirectResponse
    {
        $moderation->activate($actor, $user);

        return back()->with('status', __('admin.flash.user_activated'));
    }

    /** Email the user a password-reset link (self-service recovery on their behalf). */
    public function sendPasswordReset(#[CurrentUser] User $actor, User $user): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($actor, $user);

        Password::broker()->sendResetLink(['email' => $user->email]);

        return back()->with('status', __('admin.flash.password_reset_sent'));
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

        return back()->with('status', __('admin.flash.roles_updated'));
    }
}
