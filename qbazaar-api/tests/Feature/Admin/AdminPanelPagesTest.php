<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Resources\ActivityResource;
use App\Filament\Admin\Resources\AdResource;
use App\Filament\Admin\Resources\CategoryResource;
use App\Filament\Admin\Resources\ConversationResource;
use App\Filament\Admin\Resources\HelpArticleResource;
use App\Filament\Admin\Resources\HelpCategoryResource;
use App\Filament\Admin\Resources\LocationResource;
use App\Filament\Admin\Resources\MessageResource;
use App\Filament\Admin\Resources\ModerationRuleResource;
use App\Filament\Admin\Resources\NotificationResource;
use App\Filament\Admin\Resources\OfferResource;
use App\Filament\Admin\Resources\PageResource;
use App\Filament\Admin\Resources\ReportResource;
use App\Filament\Admin\Resources\SavedSearchResource;
use App\Filament\Admin\Resources\SupportTicketResource;
use App\Filament\Admin\Resources\UserResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/**
 * Boot-test for every admin List page plus the dashboard.
 *
 * This is the real regression net for the "Call to a member function with()
 * on null" class of bug: it renders each resource's table query end-to-end
 * with seeded relationship data, so any broken eager-load closure or stale
 * Filament v4 API surfaces here instead of in production.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    // Populate the relationship-heavy tables so the eager-loading table
    // queries actually traverse their relations (sender, conversation,
    // reporter, buyer/seller, ad, ...).
    $seller = User::factory()->create();
    $ad = $this->makeAd($seller);

    $conversation = Conversation::factory()->create([
        'ad_id' => $ad->id,
        'buyer_id' => $admin->id,
        'seller_id' => $seller->id,
    ]);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $admin->id,
    ]);

    Offer::factory()->create([
        'ad_id' => $ad->id,
        'buyer_id' => $admin->id,
        'seller_id' => $seller->id,
    ]);

    Report::factory()->create([
        'reporter_id' => $admin->id,
    ]);
});

/**
 * Resolve a resource's List ("index") page class from its getPages() map.
 *
 * @param class-string $resource
 * @return class-string
 */
function adminListPage(string $resource): string
{
    $route = $resource::getPages()['index'];

    // Filament's PageRegistration exposes the underlying page class.
    return $route->getPage();
}

it('renders the admin dashboard', function (): void {
    Livewire::test(Dashboard::class)->assertSuccessful();
});

it('renders every admin resource list page', function (string $resource): void {
    Livewire::test(adminListPage($resource))->assertSuccessful();
})->with([
    'activities' => ActivityResource::class,
    'ads' => AdResource::class,
    'categories' => CategoryResource::class,
    'conversations' => ConversationResource::class,
    'help articles' => HelpArticleResource::class,
    'help categories' => HelpCategoryResource::class,
    'locations' => LocationResource::class,
    'messages' => MessageResource::class,
    'moderation rules' => ModerationRuleResource::class,
    'notifications' => NotificationResource::class,
    'offers' => OfferResource::class,
    'pages' => PageResource::class,
    'reports' => ReportResource::class,
    'saved searches' => SavedSearchResource::class,
    'support tickets' => SupportTicketResource::class,
    'users' => UserResource::class,
]);
