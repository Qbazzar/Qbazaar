<?php

declare(strict_types=1);

use App\Enums\ReportTarget;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();
});

function staffMember(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Seeded role × admin action. `allowed` lists the roles that must get through;
 * every other staff role must be refused with 403.
 */
dataset('admin actions', [
    'dashboard' => ['get', '/admin', ['super_admin', 'moderator', 'support']],
    'list ads' => ['get', '/admin/ads', ['super_admin', 'moderator', 'support']],
    'view ad' => ['get', '/admin/ads/{ad}', ['super_admin', 'moderator', 'support']],
    'edit ad' => ['get', '/admin/ads/{ad}/edit', ['super_admin', 'moderator']],
    'approve ad' => ['post', '/admin/ads/{ad}/approve', ['super_admin', 'moderator']],
    'suspend ad' => ['post', '/admin/ads/{ad}/suspend', ['super_admin', 'moderator']],
    'feature ad' => ['post', '/admin/ads/{ad}/feature', ['super_admin', 'moderator']],
    'delete ad' => ['delete', '/admin/ads/{ad}', ['super_admin']],
    'bulk delete ads' => ['post', '/admin/ads/bulk-destroy', ['super_admin']],
    'list users' => ['get', '/admin/users', ['super_admin', 'moderator', 'support']],
    'suspend user' => ['post', '/admin/users/{user}/suspend', ['super_admin', 'moderator']],
    'activate user' => ['post', '/admin/users/{user}/activate', ['super_admin', 'moderator']],
    'reset user password' => ['post', '/admin/users/{user}/reset-password', ['super_admin']],
    'sync user roles' => ['post', '/admin/users/{user}/roles', ['super_admin']],
    'impersonate user' => ['post', '/admin/users/{user}/impersonate', ['super_admin']],
    'list roles' => ['get', '/admin/roles', ['super_admin']],
    'list reports' => ['get', '/admin/reports', ['super_admin', 'moderator', 'support']],
    'dismiss report' => ['post', '/admin/reports/{report}/dismiss', ['super_admin', 'moderator']],
    'act on reported user' => ['post', '/admin/reports/{userReport}/sanction', ['super_admin', 'moderator']],
    'moderation rules' => ['get', '/admin/moderation-rules', ['super_admin', 'moderator']],
    'create category' => ['post', '/admin/categories', ['super_admin', 'moderator']],
    'locations' => ['get', '/admin/locations', ['super_admin', 'moderator']],
    'cms pages' => ['get', '/admin/pages', ['super_admin']],
    'help articles' => ['get', '/admin/help-articles', ['super_admin']],
    'help categories' => ['get', '/admin/help-categories', ['super_admin']],
    'support desk' => ['get', '/admin/support', ['super_admin', 'moderator', 'support']],
    'conversations' => ['get', '/admin/conversations', ['super_admin', 'moderator']],
    'offers' => ['get', '/admin/offers', ['super_admin', 'moderator']],
    'saved searches' => ['get', '/admin/saved-searches', ['super_admin', 'moderator', 'support']],
    'notifications' => ['get', '/admin/notifications', ['super_admin', 'moderator', 'support']],
    'activity log' => ['get', '/admin/activity', ['super_admin']],
    'platform settings' => ['get', '/admin/settings', ['super_admin']],
    'update platform settings' => ['put', '/admin/settings', ['super_admin']],
    'finance settlements' => ['get', '/admin/finance/settlements', ['super_admin']],
    'finance withdrawals' => ['get', '/admin/finance/withdrawals', ['super_admin']],
    'finance disputes' => ['get', '/admin/finance/disputes', ['super_admin']],
    'finance statement' => ['get', '/admin/finance/users/{user}/statement', ['super_admin']],

    // Staff security, broadcasts and payments (AD-17.7, AD-17.14, AD-17.15).
    'finance orders' => ['get', '/admin/finance/orders', ['super_admin']],
    'reset staff two-factor' => ['delete', '/admin/users/{user}/two-factor', ['super_admin']],
    'permission matrix' => ['get', '/admin/roles/permissions', ['super_admin']],
    'broadcasts' => ['get', '/admin/broadcasts', ['super_admin', 'moderator', 'support']],
    'send broadcast' => ['post', '/admin/broadcasts', ['super_admin', 'moderator', 'support']],

    // Moderation tools (AD-17.4, AD-17.10)
    'review queue' => ['get', '/admin/moderation/queue', ['super_admin', 'moderator']],
    'bulk approve from the queue' => ['post', '/admin/moderation/queue/approve', ['super_admin', 'moderator']],
    'bulk reject from the queue' => ['post', '/admin/moderation/queue/reject', ['super_admin', 'moderator']],
    'staff inbox' => ['get', '/admin/inbox', ['super_admin', 'moderator', 'support']],
    'staff inbox summary' => ['get', '/admin/inbox/summary', ['super_admin', 'moderator', 'support']],
    'mark inbox read' => ['post', '/admin/inbox/read-all', ['super_admin', 'moderator', 'support']],
    'view offer' => ['get', '/admin/offers/{offer}', ['super_admin', 'moderator']],
    'cancel offer' => ['post', '/admin/offers/{offer}/cancel', ['super_admin', 'moderator']],

    // Companies, business applications and home sections (AD-17.1, AD-17.17, AD-17.2)
    'list companies' => ['get', '/admin/companies', ['super_admin', 'moderator', 'support']],
    'featured companies' => ['get', '/admin/companies/featured', ['super_admin', 'moderator', 'support']],
    'verify company' => ['post', '/admin/companies/{user}/verify', ['super_admin', 'moderator']],
    'feature company' => ['post', '/admin/companies/{user}/feature', ['super_admin', 'moderator']],
    'business applications' => ['get', '/admin/business-applications', ['super_admin', 'moderator', 'support']],
    'home sections' => ['get', '/admin/home-sections', ['super_admin', 'moderator']],
]);

it('enforces the seeded permissions for every staff role', function (string $role, string $method, string $uri, array $allowed): void {
    $owner = User::factory()->create();
    $ad = $this->makeAd($owner);
    $report = Report::factory()->create();
    $userReport = Report::factory()->create([
        'target_type' => ReportTarget::USER->value,
        'target_id' => $owner->id,
    ]);

    $path = strtr($uri, [
        '{offer}' => str_contains($uri, '{offer}') ? Offer::factory()->create()->id : '',
        '{ad}' => $ad->id,
        '{user}' => $owner->id,
        '{report}' => $report->id,
        '{userReport}' => $userReport->id,
    ]);

    actingAs(staffMember($role));
    $status = $this->{$method}($path, ['ids' => [$ad->id]])->getStatusCode();

    if (in_array($role, $allowed, true)) {
        expect($status)->not->toBe(403)->toBeLessThan(500);
    } else {
        expect($status)->toBe(403);
    }
})->with(['super_admin', 'moderator', 'support'])->with('admin actions');

it('guards every admin route except the dashboard, own profile and own inbox with a permission', function (): void {
    $unguarded = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('staff', $route->gatherMiddleware(), true))
        ->reject(fn (RoutingRoute $route): bool => in_array($route->getName(), ['admin.dashboard', 'admin.profile.edit', 'admin.profile.update'], true))
        // Every staff member manages their own two-factor sign-in (AD-17.14).
        ->reject(fn (RoutingRoute $route): bool => Str::startsWith((string) $route->getName(), 'admin.profile.two-factor.'))
        // Every staff member reads their own notifications; the work queues inside check their own permissions.
        ->reject(fn (RoutingRoute $route): bool => Str::startsWith((string) $route->getName(), 'admin.inbox.'))
        ->reject(fn (RoutingRoute $route): bool => collect($route->gatherMiddleware())
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && Str::startsWith($middleware, 'permission:')))
        ->map(fn (RoutingRoute $route): ?string => $route->getName())
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

it('refuses non-staff users', function (): void {
    actingAs(User::factory()->create());

    $this->get('/admin')->assertForbidden();
});

it('hides navigation links the staff member cannot open', function (): void {
    actingAs(staffMember('support'));

    $this->get('/admin')
        ->assertOk()
        ->assertSee(route('admin.support.index'), false)
        ->assertDontSee(route('admin.roles.index'), false)
        ->assertDontSee(route('admin.pages.index'), false)
        ->assertDontSee(route('admin.conversations.index'), false)
        ->assertDontSee(route('admin.settings.edit'), false);
});

it('does not let support delete an ad', function (): void {
    $ad = $this->makeAd(User::factory()->create());
    actingAs(staffMember('support'));

    $this->delete("/admin/ads/{$ad->id}")->assertForbidden();

    expect(Ad::query()->whereKey($ad->id)->exists())->toBeTrue();
});
