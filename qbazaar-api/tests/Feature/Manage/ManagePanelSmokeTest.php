<?php

declare(strict_types=1);

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

    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
});

/**
 * Every GET surface in the custom /manage panel must render without a server
 * error against empty tables (the "does the view/controller actually run"
 * check that a 302-gate probe can't give us). We assert < 500 rather than
 * exactly 200 so legitimate redirects/404s on missing records don't fail.
 */
it('renders every /manage index + create page', function (string $uri): void {
    $response = $this->get($uri);

    expect($response->getStatusCode())->toBeLessThan(500);
})->with([
    '/manage',
    '/manage/ads',
    '/manage/users',
    '/manage/roles',
    '/manage/reports',
    '/manage/moderation-rules',
    '/manage/moderation-rules/create',
    '/manage/categories',
    '/manage/categories/create',
    '/manage/locations',
    '/manage/locations/create',
    '/manage/pages',
    '/manage/pages/create',
    '/manage/help-categories',
    '/manage/help-categories/create',
    '/manage/help-articles',
    '/manage/help-articles/create',
    '/manage/support',
    '/manage/conversations',
    '/manage/offers',
    '/manage/saved-searches',
    '/manage/notifications',
    '/manage/activity',
]);

it('renders the ad detail page', function (): void {
    // AdFactory needs seeded categories/locations to attach.
    $this->seed(CategorySeeder::class);
    $this->seed(LocationSeeder::class);
    $ad = Ad::factory()->create();

    expect($this->get("/manage/ads/{$ad->id}")->getStatusCode())->toBeLessThan(500);
});

it('renders the user detail page', function (): void {
    $user = User::factory()->create();

    expect($this->get("/manage/users/{$user->id}")->getStatusCode())->toBeLessThan(500);
});

it('renders the report detail page', function (): void {
    $report = Report::factory()->create();

    expect($this->get("/manage/reports/{$report->id}")->getStatusCode())->toBeLessThan(500);
});

it('renders the conversation detail page', function (): void {
    $this->seed(CategorySeeder::class);
    $this->seed(LocationSeeder::class);
    $conversation = Conversation::factory()->create();

    expect($this->get("/manage/conversations/{$conversation->id}")->getStatusCode())->toBeLessThan(500);
});
