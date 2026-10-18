<?php

declare(strict_types=1);

use App\Enums\BusinessNotice;
use App\Models\User;
use App\Notifications\Business\BusinessAccountNotification;
use App\Services\Catalog\HomeFeedCache;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;

uses(RefreshDatabase::class, CountsPageQueries::class);

beforeEach(function (): void {
    $this->withoutVite();
    Notification::fake();
    Cache::flush();
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->staff = [
        'super_admin' => User::factory()->create()->assignRole('super_admin'),
        'moderator' => User::factory()->create()->assignRole('moderator'),
        'support' => User::factory()->create()->assignRole('support'),
    ];
    $this->admin = $this->staff['super_admin'];

    $this->company = User::factory()->business()->create(['full_name' => 'Ahmad Trading']);
    $this->company->businessProfile()->create(['business_name' => 'Doha Electronics', 'commercial_registration_number' => 'CR-12345']);
});

/** A business account featured at the given position. */
function featuredCompany(string $name, int $position): User
{
    $company = User::factory()->business()->create(['full_name' => $name]);
    $company->businessProfile()->create(['business_name' => $name]);
    $company->businessProfile()->update(['featured_position' => $position]);

    return $company;
}

/**
 * @return list<string> featured user ids in their stored order
 */
function featuredOrder(): array
{
    return DB::table('business_profiles')
        ->whereNotNull('featured_position')
        ->orderBy('featured_position')
        ->pluck('user_id')
        ->all();
}

describe('the companies list', function (): void {
    it('lists business accounts to every staff role', function (string $role): void {
        User::factory()->create(['full_name' => 'Private Person']);
        actingAs($this->staff[$role]);

        $this->get(route('admin.companies.index'))
            ->assertOk()
            ->assertSee('Doha Electronics')
            ->assertDontSee('Private Person');
    })->with(['super_admin', 'moderator', 'support']);

    it('filters on the verified badge and searches by CR number', function (): void {
        $verified = User::factory()->business()->create(['full_name' => 'Verified Co', 'business_verified_at' => now(), 'business_verified_by' => $this->admin->id]);
        actingAs($this->admin);

        $this->get(route('admin.companies.index', ['verification' => 'verified']))
            ->assertOk()
            ->assertSee('Verified Co')
            ->assertDontSee('Doha Electronics');

        $this->get(route('admin.companies.index', ['verification' => 'unverified']))
            ->assertOk()
            ->assertSee('Doha Electronics')
            ->assertDontSee($verified->full_name);

        $this->get(route('admin.companies.index', ['q' => 'CR-12345']))
            ->assertOk()
            ->assertSee('Doha Electronics')
            ->assertDontSee('Verified Co');

        $this->get(route('admin.companies.index', ['verification' => 'maybe']))
            ->assertRedirect()
            ->assertSessionHasErrors('verification');
    });

    it('keeps the list query count flat as companies grow', function (): void {
        // The baseline already shows a verified company, so the verifier eager load runs in both.
        $this->company->forceFill(['business_verified_at' => now(), 'business_verified_by' => $this->admin->id])->save();
        actingAs($this->admin);
        $uri = route('admin.companies.index');
        $few = $this->steadyQueryCount($uri);

        foreach (range(1, 15) as $i) {
            $company = User::factory()->business()->create(['business_verified_at' => now(), 'business_verified_by' => $this->staff['moderator']->id]);
            $company->businessProfile()->create(['business_name' => "Shop {$i}"]);
        }

        expect($this->steadyQueryCount($uri))->toBe($few)->toBeLessThanOrEqual(6);
    });

    it('serves the new lookups from indexes', function (): void {
        expect(Schema::hasIndex('users', 'users_business_verified_idx'))->toBeTrue()
            ->and(Schema::hasIndex('business_profiles', 'business_profiles_featured_idx'))->toBeTrue();
    });
});

describe('the company page', function (): void {
    it('shows the profile, the badge and the actions the staff member may take', function (): void {
        actingAs($this->admin);
        $this->get(route('admin.companies.show', $this->company))
            ->assertOk()
            ->assertSee('Doha Electronics')
            ->assertSee('CR-12345')
            ->assertSee(route('admin.companies.verify', $this->company), false)
            ->assertSee(route('admin.companies.feature', $this->company), false);

        actingAs($this->staff['support']);
        $this->get(route('admin.companies.show', $this->company))
            ->assertOk()
            ->assertSee('Doha Electronics')
            ->assertDontSee(route('admin.companies.verify', $this->company), false)
            ->assertDontSee(route('admin.companies.feature', $this->company), false);
    });

    it('is only for business accounts', function (): void {
        actingAs($this->admin);

        $this->get(route('admin.companies.show', User::factory()->create()))->assertNotFound();
    });
});

describe('the verified badge', function (): void {
    it('verifies a company, records who and when, audits once and notifies the owner', function (): void {
        $feedKey = app(HomeFeedCache::class)->key('ar');
        Cache::put($feedKey, ['stale' => true]);
        actingAs($this->staff['moderator']);

        $this->post(route('admin.companies.verify', $this->company))
            ->assertRedirect()
            ->assertSessionHas('status', __('admin.companies.flash.verified'));

        $company = $this->company->fresh();
        expect($company?->isVerifiedBusiness())->toBeTrue()
            ->and($company?->business_verified_by)->toBe($this->staff['moderator']->id)
            ->and($company?->business_verified_at)->not->toBeNull()
            ->and(Cache::has($feedKey))->toBeFalse()
            ->and(Activity::query()->where('log_name', 'admin')->count())->toBe(1);

        $audit = Activity::query()->where('event', 'admin.companies.verify')->sole();
        expect($audit->causer_id)->toBe($this->staff['moderator']->id)
            ->and($audit->subject_id)->toBe($this->company->id);

        Notification::assertSentTo($this->company, BusinessAccountNotification::class, fn (BusinessAccountNotification $notification): bool => $notification->notice === BusinessNotice::VERIFICATION_GRANTED);

        $this->get(route('admin.companies.show', $this->company))
            ->assertOk()
            ->assertSee(__('admin.companies.verification.verified_by'))
            ->assertSee(__('admin.timeline.events.companies.verify'));
    });

    it('refuses to verify twice or to verify a private account', function (): void {
        $this->company->forceFill(['business_verified_at' => now(), 'business_verified_by' => $this->admin->id])->save();
        actingAs($this->admin);

        $this->post(route('admin.companies.verify', $this->company))->assertRedirect()->assertSessionHas('error');

        $private = User::factory()->create();
        $this->post(route('admin.companies.verify', $private))->assertRedirect()->assertSessionHas('error');

        expect($private->fresh()?->business_verified_at)->toBeNull()
            ->and(Activity::query()->where('event', 'admin.companies.verify')->exists())->toBeFalse();
        Notification::assertNothingSent();
    });

    it('revokes the badge with a required reason the owner reads', function (): void {
        $this->company->forceFill(['business_verified_at' => now(), 'business_verified_by' => $this->admin->id])->save();
        actingAs($this->admin);

        $this->post(route('admin.companies.revoke-verification', $this->company), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->post(route('admin.companies.revoke-verification', $this->company), ['reason' => '<i>The licence expired</i>'])
            ->assertRedirect()
            ->assertSessionHas('status', __('admin.companies.flash.revoked'));

        $company = $this->company->fresh();
        expect($company?->isVerifiedBusiness())->toBeFalse()
            ->and($company?->business_verified_by)->toBeNull();

        $audit = Activity::query()->where('event', 'admin.companies.revoke-verification')->sole();
        expect($audit->properties->get('reason'))->toBe('The licence expired');

        Notification::assertSentTo($this->company, BusinessAccountNotification::class, fn (BusinessAccountNotification $notification): bool => $notification->notice === BusinessNotice::VERIFICATION_REVOKED
            && $notification->reason === 'The licence expired');
    });

    it('cannot revoke a badge the company does not have', function (): void {
        actingAs($this->admin);

        $this->post(route('admin.companies.revoke-verification', $this->company), ['reason' => 'Fake documents'])
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('lets only staff who verify companies change the badge', function (): void {
        actingAs($this->staff['support']);

        $this->post(route('admin.companies.verify', $this->company))->assertForbidden();
        $this->post(route('admin.companies.revoke-verification', $this->company), ['reason' => 'Fake documents'])->assertForbidden();

        expect($this->company->fresh()?->business_verified_at)->toBeNull();
    });
});

describe('featured companies', function (): void {
    it('features companies at the end of the list and removes them without gaps', function (): void {
        $first = featuredCompany('First Shop', 1);
        $second = featuredCompany('Second Shop', 2);
        actingAs($this->admin);

        $this->post(route('admin.companies.feature', $this->company))
            ->assertRedirect()
            ->assertSessionHas('status', __('admin.companies.flash.featured'));
        expect(featuredOrder())->toBe([$first->id, $second->id, $this->company->id]);

        // A stale page featuring it again changes nothing and writes no history.
        $this->post(route('admin.companies.feature', $this->company))
            ->assertRedirect()
            ->assertSessionHas('error', __('errors.business.already.featured'));
        expect(featuredOrder())->toBe([$first->id, $second->id, $this->company->id]);

        $this->post(route('admin.companies.unfeature', $first))
            ->assertRedirect()
            ->assertSessionHas('status', __('admin.companies.flash.unfeatured'));
        expect(featuredOrder())->toBe([$second->id, $this->company->id])
            ->and(DB::table('business_profiles')->where('user_id', $this->company->id)->value('featured_position'))->toBe(2)
            ->and(Activity::query()->where('event', 'admin.companies.feature')->count())->toBe(1)
            ->and(Activity::query()->where('event', 'admin.companies.unfeature')->count())->toBe(1);

        $this->post(route('admin.companies.unfeature', $first))
            ->assertRedirect()
            ->assertSessionHas('error', __('errors.business.not.featured'));
        expect(Activity::query()->where('event', 'admin.companies.unfeature')->count())->toBe(1);
    });

    it('reorders the featured companies by the positions staff type', function (): void {
        $a = featuredCompany('Shop A', 1);
        $b = featuredCompany('Shop B', 2);
        $c = featuredCompany('Shop C', 3);
        actingAs($this->staff['moderator']);

        $this->put(route('admin.companies.featured.reorder'), ['positions' => [$c->id => 1, $a->id => 3, $b->id => null]])
            ->assertRedirect(route('admin.companies.featured.index'))
            ->assertSessionHas('status', __('admin.companies.flash.reordered'));

        expect(featuredOrder())->toBe([$c->id, $b->id, $a->id])
            ->and(DB::table('business_profiles')->whereNotNull('featured_position')->orderBy('featured_position')->pluck('featured_position')->all())->toBe([1, 2, 3]);

        $this->get(route('admin.companies.featured.index'))
            ->assertOk()
            ->assertSeeInOrder(['Shop C', 'Shop B', 'Shop A']);
    });

    it('validates the typed positions', function (): void {
        featuredCompany('Shop A', 1);
        actingAs($this->admin);

        $this->put(route('admin.companies.featured.reorder'), [])->assertSessionHasErrors('positions');
        $this->put(route('admin.companies.featured.reorder'), ['positions' => ['x' => 0]])->assertSessionHasErrors('positions.x');
        $this->put(route('admin.companies.featured.reorder'), ['positions' => ['x' => 'first']])->assertSessionHasErrors('positions.x');
    });

    it('caps the featured list and only features business accounts', function (): void {
        config(['qbazaar.business.featured_max' => 1]);
        featuredCompany('Shop A', 1);
        actingAs($this->admin);

        $this->post(route('admin.companies.feature', $this->company))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.companies.feature', User::factory()->create()))->assertRedirect()->assertSessionHas('error');

        expect(featuredOrder())->toHaveCount(1);
    });

    it('lets only staff who feature companies change the list', function (): void {
        $a = featuredCompany('Shop A', 1);
        actingAs($this->staff['support']);

        $this->get(route('admin.companies.featured.index'))->assertOk()->assertSee('Shop A');
        $this->post(route('admin.companies.feature', $this->company))->assertForbidden();
        $this->post(route('admin.companies.unfeature', $a))->assertForbidden();
        $this->put(route('admin.companies.featured.reorder'), ['positions' => [$a->id => 1]])->assertForbidden();
    });

    it('keeps the featured page query count flat as the list grows', function (): void {
        actingAs($this->admin);
        $uri = route('admin.companies.featured.index');

        featuredCompany('Shop 1', 1);
        $few = $this->steadyQueryCount($uri);

        foreach (range(2, 12) as $i) {
            featuredCompany("Shop {$i}", $i);
        }

        expect($this->steadyQueryCount($uri))->toBe($few)->toBeLessThanOrEqual(4);
    });
});
