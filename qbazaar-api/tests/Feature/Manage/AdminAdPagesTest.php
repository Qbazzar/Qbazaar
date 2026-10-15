<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\PromotionStatus;
use App\Enums\ReportCategory;
use App\Enums\ReportTarget;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Models\Conversation;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\Ads\AdStatusHistory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->admin = User::factory()->create(['full_name' => 'أحمد المشرف'])->assignRole('super_admin');
    $this->seller = User::factory()->create(['full_name' => 'محمد الكعبي', 'phone' => '+97455123456']);
    $this->ad = $this->makeAd($this->seller, [
        'title' => 'آيفون 14 برو ماكس',
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
        'views_count' => 1240,
        'favorites_count' => 37,
    ]);
});

/**
 * Gives the ad $count of every related row the review page reads: reports with
 * reporters who have photos, promotions, conversations, status history rows and
 * other ads by the same seller.
 */
function giveAdPageRelations(Ad $ad, User $admin, int $count): void
{
    foreach (range(1, $count) as $i) {
        $reporter = User::factory()->create();
        $reporter->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('avatar');

        Report::factory()->create([
            'reporter_id' => $reporter->id,
            'target_type' => ReportTarget::AD->value,
            'target_id' => $ad->id,
            'category' => ReportCategory::FRAUD->value,
            'created_at' => now()->subMinutes($i),
        ]);

        AdPromotion::factory()->create([
            'ad_id' => $ad->id,
            'user_id' => $ad->user_id,
            'status' => PromotionStatus::EXPIRED->value,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(13),
        ]);

        Conversation::factory()->create(['ad_id' => $ad->id, 'seller_id' => $ad->user_id]);

        Ad::factory()->create([
            'user_id' => $ad->user_id,
            'title' => sprintf('إعلان آخر %02d', $i),
            'category_id' => $ad->category_id,
            'location_id' => $ad->location_id,
            'created_at' => now()->subMinutes($i),
        ]);

        activity('ad')->performedOn($ad)->causedBy($admin)->event('status_changed')
            ->withProperties(['old' => ['status' => 'active'], 'new' => ['status' => 'blocked']])
            ->log('Ad status changed');
    }
}

/** Queries of a steady request: a first one warms the permission cache and settings. */
function adPageQueryCount(string $uri): int
{
    test()->get($uri)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($uri)->assertOk();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

describe('ads.show', function (): void {
    it('renders in the page shell for every role that can open ads, with the actions each may use', function (string $role, array $visible, array $hidden): void {
        actingAs(User::factory()->create()->assignRole($role));

        $html = $this->get(route('admin.ads.show', $this->ad))
            ->assertOk()
            ->assertSee('data-qb-page', false)
            ->assertSee('آيفون 14 برو ماكس')
            ->assertSee('محمد الكعبي')
            ->assertSee('+974 5512 3456')
            ->assertSee('1,240')
            ->assertDontSee(__('admin.shell.widget_failed'))
            ->getContent();

        expect(substr_count($html, '<h1'))->toBe(1)
            ->and($html)->toContain('aria-label="إشراف ومعلومات الإعلان"');
        foreach ($visible as $needle) {
            expect($html)->toContain($needle);
        }
        foreach ($hidden as $needle) {
            expect($html)->not->toContain($needle);
        }
    })->with([
        'super_admin' => ['super_admin', ['id="ad-moderation-title"', 'id="ad-reports-title"', '/edit"', '/suspend"', '/feature"', 'حذف الإعلان'], []],
        'moderator' => ['moderator', ['id="ad-moderation-title"', 'id="ad-reports-title"', '/edit"', '/suspend"', '/feature"'], ['حذف الإعلان']],
        'support' => ['support', ['id="ad-reports-title"', 'id="ad-seller-title"', 'id="ad-history-title"'], ['id="ad-moderation-title"', '/edit"', '/suspend"', 'حذف الإعلان']],
    ]);

    it('offers approve and reject on a pending ad and suspension on a live one', function (): void {
        actingAs($this->admin);
        $pending = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);

        $this->get(route('admin.ads.show', $pending))
            ->assertOk()
            ->assertSee(route('admin.ads.approve', $pending), false)
            ->assertSee(route('admin.ads.reject', $pending), false)
            ->assertDontSee(route('admin.ads.suspend', $pending), false)
            ->assertSee(__('admin.ad_review.auto_check'));

        $this->get(route('admin.ads.show', $this->ad))
            ->assertOk()
            ->assertSee(route('admin.ads.suspend', $this->ad), false)
            ->assertSee(route('admin.ads.force-expire', $this->ad), false)
            ->assertDontSee(route('admin.ads.approve', $this->ad), false);
    });

    it('bounds every related list to five with a link to the rest', function (): void {
        actingAs($this->admin);
        giveAdPageRelations($this->ad, $this->admin, 7);

        $html = $this->get(route('admin.ads.show', $this->ad))
            ->assertOk()
            ->assertSee('عرض الكل (7)')
            ->assertSee(route('admin.reports.index', ['q' => $this->ad->id]), false)
            ->assertSee(route('admin.users.show', ['user' => $this->seller, 'tab' => 'ads']), false)
            ->assertSee('آخر 5 من 7')
            ->assertSee('7 بلاغات مفتوحة')
            ->assertSee('إيقاف الإعلان')
            ->assertSee('أحمد المشرف')
            ->getContent();

        $history = (string) str($html)->after('id="ad-history-title"')->before('</section>');

        expect(substr_count($html, 'إعلان آخر '))->toBe(5)
            ->and(substr_count($html, 'href="' . url('/admin/reports') . '/'))->toBe(5)
            ->and(substr_count($history, '<li '))->toBe(AdStatusHistory::DEFAULT_LIMIT);
    });

    it('names status moves after what happened and who did it', function (): void {
        actingAs($this->admin);
        Activity::query()->delete();
        activity('ad')->performedOn($this->ad)->causedBy($this->admin)->event('status_changed')
            ->withProperties(['old' => ['status' => 'pending'], 'new' => ['status' => 'active']])->log('Ad status changed');
        // The expiry job runs without a signed-in user, so the observer logs the owner as causer.
        activity('ad')->performedOn($this->ad)->causedBy($this->seller)->event('status_changed')
            ->withProperties(['old' => ['status' => 'active'], 'new' => ['status' => 'expired']])->log('Ad status changed');
        activity('ad')->performedOn($this->ad)->causedBy($this->seller)->event('status_changed')
            ->withProperties(['old' => ['status' => 'active'], 'new' => ['status' => 'sold']])->log('Ad status changed');

        $html = $this->get(route('admin.ads.show', $this->ad))->assertOk()->getContent();
        $history = (string) str($html)->after('id="ad-history-title"')->before('</section>');

        expect($history)->toContain('اعتماد الإعلان')
            ->toContain('أحمد المشرف')
            ->toContain('انتهاء الصلاحية')
            ->toContain(__('admin.timeline.system'))
            ->toContain('بيع الإعلان')
            ->toContain('البائع');
    });

    it('keeps the page up when one side widget fails', function (): void {
        actingAs($this->admin);
        Schema::drop('activity_log');

        $this->get(route('admin.ads.show', $this->ad))
            ->assertOk()
            ->assertSee(__('admin.shell.widget_failed'))
            ->assertSee('id="ad-seller-title"', false);
    });

    it('stays within a fixed query budget whatever the number of related rows', function (): void {
        actingAs($this->admin);
        $uri = route('admin.ads.show', $this->ad);

        giveAdPageRelations($this->ad, $this->admin, 1);
        $withOne = adPageQueryCount($uri);

        giveAdPageRelations($this->ad, $this->admin, 24);
        $withTwentyFive = adPageQueryCount($uri);

        expect($withTwentyFive)->toBe($withOne)
            // 14 for the page itself plus the deal panel's lists (orders, chats with their eager loads, offers).
            ->and($withOne)->toBeLessThanOrEqual(24);
    });
});

describe('ads.edit', function (): void {
    it('renders the form in the page shell for the roles that may edit ads', function (string $role): void {
        actingAs(User::factory()->create()->assignRole($role));

        $html = $this->get(route('admin.ads.edit', $this->ad))
            ->assertOk()
            ->assertSee('حفظ التعديلات')
            ->assertSee('محمد الكعبي')
            ->assertDontSee(__('admin.shell.widget_failed'))
            ->getContent();

        $form = (string) str($html)->after('<form data-qb-page')->before('</form>');

        expect(substr_count($html, '<h1'))->toBe(1)
            ->and($form)->toContain('name="_method" value="PUT"')
            ->toContain('action="' . route('admin.ads.update', $this->ad) . '"')
            ->toContain('name="title"')
            // The featured box sits in the side column and still posts with Save.
            ->toContain('name="featured"')
            ->toContain('<aside');
    })->with(['super_admin', 'moderator']);

    it('is closed to staff who cannot edit ads', function (): void {
        actingAs(User::factory()->create()->assignRole('support'));

        $this->get(route('admin.ads.edit', $this->ad))->assertForbidden();
    });

    it('keeps the typed values and lists the errors above the form when saving fails', function (): void {
        actingAs($this->admin);
        $edit = route('admin.ads.edit', $this->ad);

        $this->from($edit)
            ->followingRedirects()
            ->put(route('admin.ads.update', $this->ad), [
                'title' => '',
                'description' => 'وصف جديد لم يُحفظ بعد',
                'category_id' => $this->ad->category_id,
                'location_id' => $this->ad->location_id,
                'price_type' => 'not-a-type',
            ])
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('data-qb-autofocus', false)
            ->assertSee('href="#title"', false)
            ->assertSee('href="#price_type"', false)
            ->assertSee('وصف جديد لم يُحفظ بعد');

        expect($this->ad->fresh()->title)->toBe('آيفون 14 برو ماكس');
    });

    it('saves the content and the featured flag from the side column', function (): void {
        actingAs($this->admin);

        $this->put(route('admin.ads.update', $this->ad), [
            'title' => 'آيفون 15',
            'description' => 'وصف محدّث',
            'category_id' => $this->ad->category_id,
            'location_id' => $this->ad->location_id,
            'price' => '3450',
            'price_type' => $this->ad->price_type->value,
            'featured' => '1',
        ])->assertRedirect(route('admin.ads.show', $this->ad))->assertSessionHasNoErrors();

        $fresh = $this->ad->fresh();
        expect($fresh->title)->toBe('آيفون 15')->and($fresh->featured)->toBeTrue();
    });

    it('stays within a fixed query budget whatever the length of the history', function (): void {
        actingAs($this->admin);
        $uri = route('admin.ads.edit', $this->ad);

        giveAdPageRelations($this->ad, $this->admin, 1);
        $withOne = adPageQueryCount($uri);

        giveAdPageRelations($this->ad, $this->admin, 24);
        $withTwentyFive = adPageQueryCount($uri);

        expect($withTwentyFive)->toBe($withOne)
            ->and($withOne)->toBeLessThanOrEqual(10);
    });
});
