<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Filament\Admin\Pages\Dashboard;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('broadcasts a system announcement to all users', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    $recipients = User::factory()->count(2)->create(['status' => UserStatus::ACTIVE->value]);

    Livewire::test(Dashboard::class)
        ->callAction('send_announcement', [
            'title' => 'Maintenance window',
            'body' => 'We will be down at midnight.',
            'target' => 'all_users',
        ]);

    Notification::assertSentTo($recipients->all(), SystemAnnouncementNotification::class);
    Notification::assertSentTo($admin, SystemAnnouncementNotification::class);
});

it('targets only users with active ads', function (): void {
    Notification::fake();
    $this->seedReferenceData();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    $seller = User::factory()->create(['status' => UserStatus::ACTIVE->value]);
    $this->makeAd($seller, ['status' => AdStatus::ACTIVE->value]);

    $idleUser = User::factory()->create(['status' => UserStatus::ACTIVE->value]);

    Livewire::test(Dashboard::class)
        ->callAction('send_announcement', [
            'title' => 'Boost your listing',
            'body' => 'Feature your ad today.',
            'target' => 'users_with_active_ads',
        ]);

    Notification::assertSentTo($seller, SystemAnnouncementNotification::class);
    Notification::assertNotSentTo($idleUser, SystemAnnouncementNotification::class);
});

it('shows the announcement action to support staff (who hold the broadcast permission)', function (): void {
    // Every panel-accessing role (super_admin, moderator, support) is seeded
    // with notifications.broadcast, so support — the lowest-privilege staff —
    // still sees the action. This is the gate's positive control.
    $support = User::factory()->create();
    $support->assignRole('support');
    actingAs($support);

    expect($support->can('notifications.broadcast'))->toBeTrue();

    Livewire::test(Dashboard::class)
        ->assertActionVisible('send_announcement');
});
