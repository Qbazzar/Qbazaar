<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Users\SuspendUserAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\StaffHierarchy;
use App\Services\Auth\RefreshTokenService;
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

    public function show(Request $request, User $user): View
    {
        $user->load('roles');
        $user->loadCount('ads');

        $canManageUser = $this->hierarchy->canManage($request->user(), $user);

        return view('admin.users.show', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'canManageUser' => $canManageUser,
            'canManageRoles' => $canManageUser && $request->user()->can('roles.manage'),
        ]);
    }

    public function suspend(Request $request, User $user, SuspendUserAction $suspendUser): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($request->user(), $user);

        $suspendUser->execute($user);

        return back()->with('status', 'تم إيقاف المستخدم.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($request->user(), $user);

        $user->forceFill(['status' => UserStatus::ACTIVE])->save();

        return back()->with('status', 'تم تفعيل المستخدم.');
    }

    /** Email the user a password-reset link (self-service recovery on their behalf). */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($request->user(), $user);

        Password::broker()->sendResetLink(['email' => $user->email]);

        return back()->with('status', 'تم إرسال رابط إعادة تعيين كلمة المرور للمستخدم.');
    }

    /**
     * Impersonate a user: mint a real token pair for them and hand it to the
     * Next.js web app so the admin browses the marketplace as that user.
     *
     * Staff accounts can't be impersonated (avoids privilege confusion). Tokens
     * ride in the URL *fragment* (never sent to servers or logs); the web
     * /impersonate page consumes and clears them immediately.
     */
    public function impersonate(Request $request, User $user, RefreshTokenService $tokens): RedirectResponse
    {
        if ($user->isStaff()) {
            return back()->with('error', 'لا يمكن انتحال هوية عضو من فريق الإدارة.');
        }

        $pair = $tokens->issue($user, null, $request->ip(), 'impersonation');

        $webUrl = rtrim((string) config('qbazaar.web_url', config('app.url')), '/');
        $fragment = http_build_query([
            'access' => $pair->accessToken,
            'refresh' => $pair->refreshToken,
            'name' => $user->full_name,
        ]);

        return redirect()->away("{$webUrl}/impersonate#{$fragment}");
    }

    /** Sync a user's roles from the checkbox list. */
    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $this->hierarchy->ensureCanManage($request->user(), $user);

        $available = Role::pluck('name')->all();

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'in:' . implode(',', $available)],
        ]);

        $roles = $data['roles'] ?? [];
        $this->hierarchy->ensureCanGrantRoles($request->user(), $roles);

        $user->syncRoles($roles);

        return back()->with('status', 'تم تحديث أدوار المستخدم.');
    }
}
