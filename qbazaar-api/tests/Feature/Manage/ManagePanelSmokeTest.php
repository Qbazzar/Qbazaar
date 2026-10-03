<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The compiled Vite manifest only exists after a build (created on deploy);
    // stub it so @vite in the layout doesn't 500 the whole panel under test.
    $this->withoutVite();

    // Keep the smoke test hermetic: don't push factory models to Meilisearch
    // (Scout) — this suite only exercises Blade rendering, not search.
    config(['scout.driver' => null]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
});

/**
 * Every GET surface in the custom /admin panel must render without a server
 * error against empty tables (the "does the view/controller actually run"
 * check that a 302-gate probe can't give us). We assert < 500 rather than
 * exactly 200 so legitimate redirects/404s on missing records don't fail.
 */
it('renders every /admin index + create page', function (string $uri): void {
    $response = $this->get($uri);

    expect($response->getStatusCode())->toBeLessThan(500);
})->with([
    '/admin',
    '/admin/profile',
    '/admin/ads',
    '/admin/users',
    '/admin/roles',
    '/admin/reports',
    '/admin/moderation-rules',
    '/admin/moderation-rules/create',
    '/admin/categories',
    '/admin/categories/create',
    '/admin/locations',
    '/admin/locations/create',
    '/admin/pages',
    '/admin/pages/create',
    '/admin/help-categories',
    '/admin/help-categories/create',
    '/admin/help-articles',
    '/admin/help-articles/create',
    '/admin/support',
    '/admin/conversations',
    '/admin/offers',
    '/admin/saved-searches',
    '/admin/notifications',
    '/admin/activity',
]);

it('renders the ad detail page', function (): void {
    // AdFactory needs seeded categories/locations to attach.
    $this->seed(CategorySeeder::class);
    $this->seed(LocationSeeder::class);
    $ad = Ad::factory()->create();

    expect($this->get("/admin/ads/{$ad->id}")->getStatusCode())->toBeLessThan(500);
    expect($this->get("/admin/ads/{$ad->id}/edit")->getStatusCode())->toBeLessThan(500);
});

it('renders the user detail page', function (): void {
    $user = User::factory()->create();

    expect($this->get("/admin/users/{$user->id}")->getStatusCode())->toBeLessThan(500);
});

it('renders the report detail page', function (): void {
    $report = Report::factory()->create();

    expect($this->get("/admin/reports/{$report->id}")->getStatusCode())->toBeLessThan(500);
});

it('renders the conversation detail page', function (): void {
    $this->seed(CategorySeeder::class);
    $this->seed(LocationSeeder::class);
    $conversation = Conversation::factory()->create();

    expect($this->get("/admin/conversations/{$conversation->id}")->getStatusCode())->toBeLessThan(500);
});

it('renders the shared layout chrome on every /admin page', function (string $uri): void {
    $this->get($uri)
        ->assertOk()
        ->assertSee('aria-label="القائمة الرئيسية"', false)
        ->assertSee('aria-controls="admin-nav" aria-expanded="false"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('id="qb-confirm"', false)
        ->assertSee('id="main"', false);
})->with([
    '/admin',
    '/admin/settings',
    '/admin/categories/create',
    '/admin/locations/create',
    '/admin/pages/create',
    '/admin/help-categories/create',
    '/admin/help-articles/create',
    '/admin/moderation-rules/create',
]);

it('renders every index page through the filter bar and responsive table', function (string $uri): void {
    $this->get($uri)
        ->assertOk()
        ->assertSee('role="search"', false)
        ->assertSee('<label for="filter-q" class="sr-only">', false)
        ->assertSee('max-md:hidden', false)
        ->assertDontSee('onsubmit=', false);
})->with([
    '/admin/ads',
    '/admin/users',
    '/admin/roles',
    '/admin/reports',
    '/admin/moderation-rules',
    '/admin/categories',
    '/admin/locations',
    '/admin/pages',
    '/admin/help-categories',
    '/admin/help-articles',
    '/admin/support',
    '/admin/conversations',
    '/admin/offers',
    '/admin/saved-searches',
    '/admin/notifications',
    '/admin/activity',
]);

it('renders populated index rows as stacked cards with enum badges and visible actions', function (): void {
    $this->seed(CategorySeeder::class);
    $this->seed(LocationSeeder::class);
    $ad = Ad::factory()->create();
    $report = Report::factory()->create();

    $this->get('/admin/ads')
        ->assertOk()
        ->assertSee('md:table-row', false)
        ->assertSee('data-bulk-item', false)
        ->assertSee('id="qb-bulk-form"', false)
        ->assertSee($ad->status->label()['ar'])
        ->assertSee('aria-label="تعديل"', false);

    $this->get('/admin/reports')
        ->assertOk()
        ->assertSee($report->status->label()['ar'])
        ->assertSee('aria-label="عرض"', false);

    $this->get('/admin/users')
        ->assertOk()
        ->assertSee(UserStatus::ACTIVE->label()['ar']);
});

it('confirms destructive actions through the dialog instead of window.confirm', function (): void {
    $this->seed(CategorySeeder::class);
    $this->seed(LocationSeeder::class);
    $ad = Ad::factory()->create();

    $this->get("/admin/ads/{$ad->id}")
        ->assertOk()
        ->assertSee('data-confirm="حذف هذا الإعلان نهائياً؟ لا يمكن التراجع."', false)
        ->assertDontSee('return confirm(', false);
});

it('shows validation errors inline only, not again as a toast', function (): void {
    $this->from('/admin/moderation-rules/create')
        ->followingRedirects()
        ->post('/admin/moderation-rules', [])
        ->assertOk()
        ->assertSee('id="value-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertDontSee('data-toast', false);
});
