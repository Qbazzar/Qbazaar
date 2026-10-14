<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\UserDetailTabs;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class, CountsPageQueries::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->customer = User::factory()->create(['full_name' => 'سارة العميلة', 'phone' => '+97455123456']);
});

/** Ads, orders on both sides (some counterparts with photos) and audit rows about the customer. */
function givePeoplePageHistory(User $customer, User $admin, int $count): void
{
    foreach (range(1, $count) as $i) {
        Ad::factory()->create([
            'user_id' => $customer->id,
            'title' => sprintf('إعلان رقم %02d', $i),
            'category_id' => Category::query()->value('id'),
            'location_id' => Location::query()->value('id'),
            'created_at' => now()->subMinutes(100 - $i),
        ]);

        $counterpart = User::factory()->create();
        if ($i % 5 === 1) {
            $counterpart->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('avatar');
        }
        Order::factory()->create([
            'ad_id' => null,
            'buyer_id' => $i % 2 === 0 ? $customer->id : $counterpart->id,
            'seller_id' => $i % 2 === 0 ? $counterpart->id : $customer->id,
        ]);

        activity('admin')->performedOn($customer)->causedBy($admin)->event('admin.users.suspend')->log('admin.users.suspend');
    }
}

describe('users.show', function (): void {
    it('renders in the shell for every role that can open users, with one h1 and the tabs it may see', function (string $role, array $visible, array $hidden): void {
        actingAs(User::factory()->create()->assignRole($role));

        $html = $this->get(route('admin.users.show', $this->customer))
            ->assertOk()
            ->assertSee('data-qb-page', false)
            ->assertSee('data-tabs="user-tabs"', false)
            ->assertSee('سارة العميلة')
            ->assertSee('+974 5512 3456')
            ->getContent();

        expect(substr_count($html, '<h1'))->toBe(1);
        foreach ($visible as $tab) {
            expect($html)->toContain("id=\"user-tabs-tab-{$tab}\"");
        }
        foreach ($hidden as $tab) {
            expect($html)->not->toContain("id=\"user-tabs-tab-{$tab}\"");
        }
    })->with([
        'super_admin' => ['super_admin', ['profile', 'ads', 'orders', 'wallet'], []],
        'moderator' => ['moderator', ['profile', 'ads'], ['orders', 'wallet']],
        'support' => ['support', ['profile', 'ads'], ['orders', 'wallet']],
    ]);

    it('renders only the requested tab and leaves the others to load when opened', function (): void {
        $this->makeAd($this->customer, ['title' => 'آيفون للبيع']);
        actingAs($this->admin);

        $this->get(route('admin.users.show', [$this->customer, 'tab' => 'ads']))
            ->assertOk()
            ->assertSee('آيفون للبيع')
            ->assertSee('data-active="ads"', false)
            ->assertDontSee('data-qb-lazy="' . route('admin.users.tab', [$this->customer, 'ads']) . '"', false)
            ->assertSee('data-qb-lazy="' . route('admin.users.tab', [$this->customer, 'orders']) . '"', false)
            ->assertSee('data-qb-lazy="' . route('admin.users.tab', [$this->customer, 'wallet']) . '"', false)
            ->assertDontSee('أرصدة المحفظة')
            ->assertDontSee('طلبات المستخدم');
    });

    it('falls back to the profile tab for an unknown tab or one the role cannot open', function (): void {
        actingAs(User::factory()->create()->assignRole('support'));

        $this->get(route('admin.users.show', [$this->customer, 'tab' => 'wallet']))
            ->assertOk()
            ->assertSee('data-active="profile"', false)
            ->assertSee('بيانات الحساب')
            ->assertDontSee('أرصدة المحفظة');
    });

    it('serves each tab panel on its own and refuses tabs the role cannot open', function (): void {
        $this->makeAd($this->customer, ['title' => 'دراجة هوائية']);
        actingAs($this->admin);

        $panel = $this->get(route('admin.users.tab', [$this->customer, 'ads']))->assertOk();
        $panel->assertSee('دراجة هوائية')->assertDontSee('<html', false)->assertDontSee('<h1', false);
        $this->get(route('admin.users.tab', [$this->customer, 'wallet']))->assertOk()->assertSee('أرصدة المحفظة');
        $this->get(route('admin.users.tab', [$this->customer, 'orders']))->assertOk()->assertSee('لا توجد طلبات لهذا المستخدم.');
        $this->get("/admin/users/{$this->customer->id}/tabs/secrets")->assertNotFound();

        actingAs(User::factory()->create()->assignRole('moderator'));
        $this->get(route('admin.users.tab', [$this->customer, 'orders']))->assertForbidden();
        $this->get(route('admin.users.tab', [$this->customer, 'wallet']))->assertForbidden();
        $this->get(route('admin.users.tab', [$this->customer, 'ads']))->assertOk();
    });

    it('opens the tab named in the query string, as the ad page links to the seller ads', function (): void {
        $this->makeAd($this->customer, ['title' => 'غسالة ملابس']);
        actingAs($this->admin);

        $this->get(route('admin.users.show', [$this->customer, 'tab' => 'ads']))
            ->assertOk()
            ->assertSee('data-active="ads"', false)
            ->assertSee('غسالة ملابس');
    });

    it('serves the profile and wallet panels lazily through the tab route', function (): void {
        actingAs($this->admin);

        $this->get(route('admin.users.tab', [$this->customer, 'profile']))
            ->assertOk()
            ->assertSee('سارة العميلة')
            ->assertSee('بيانات الحساب')
            ->assertDontSee('<html', false)
            ->assertDontSee('<h1', false);
        $this->get(route('admin.users.tab', [$this->customer, 'wallet']))
            ->assertOk()
            ->assertSee('أرصدة المحفظة')
            ->assertDontSee('<html', false)
            ->assertDontSee('<h1', false);

        actingAs(User::factory()->create()->assignRole('support'));
        $this->get(route('admin.users.tab', [$this->customer, 'profile']))->assertOk()->assertSee('بيانات الحساب');
        $this->get(route('admin.users.tab', [$this->customer, 'wallet']))->assertForbidden();
    });

    it('shows the business contact details only to staff who can edit users', function (): void {
        $shop = User::factory()->business()->create();
        $shop->businessProfile()->create([
            'business_name' => 'متجر النور',
            'legal_name' => 'شركة النور القانونية',
            'commercial_registration_number' => 'CR-778899',
            'contact_phone' => '+97444112233',
            'contact_email' => 'contact@noor.example',
            'website' => 'https://noor.example',
            'address' => 'الدوحة، شارع المرقاب',
        ]);
        $private = ['شركة النور القانونية', 'CR-778899', 'contact@noor.example', 'https://noor.example', 'الدوحة، شارع المرقاب'];

        actingAs($this->admin);
        $full = $this->get(route('admin.users.tab', [$shop, 'profile']))->assertOk()->assertSee('متجر النور');
        foreach ($private as $value) {
            $full->assertSee($value);
        }

        actingAs(User::factory()->create()->assignRole('support'));
        $restricted = $this->get(route('admin.users.tab', [$shop, 'profile']))
            ->assertOk()
            ->assertSee('متجر النور')
            ->assertSee('مقيّدة');
        foreach ($private as $value) {
            $restricted->assertDontSee($value);
        }
    });

    it('merges buyer and seller orders newest first across pages without double counting', function (): void {
        $other = User::factory()->create();
        $ids = [];
        foreach (range(1, 14) as $i) {
            $ids[$i] = Order::factory()->create([
                'ad_id' => null,
                'buyer_id' => $i % 2 === 0 ? $this->customer->id : $other->id,
                'seller_id' => $i % 2 === 0 ? $other->id : $this->customer->id,
                'created_at' => now()->subMinutes(100 - $i),
            ])->id;
        }
        Order::factory()->create(['ad_id' => null, 'buyer_id' => $other->id, 'seller_id' => $other->id]);
        $tabs = app(UserDetailTabs::class);

        $first = $tabs->panel('orders', $this->customer)['orders'];
        request()->query->set('page', 2);
        $second = $tabs->panel('orders', $this->customer)['orders'];

        expect($first->total())->toBe(14)
            ->and($first->pluck('id')->all())->toBe([$ids[14], $ids[13], $ids[12], $ids[11], $ids[10], $ids[9], $ids[8], $ids[7], $ids[6], $ids[5]])
            ->and($second->pluck('id')->all())->toBe([$ids[4], $ids[3], $ids[2], $ids[1]]);
    });

    it('pages a long tab and links the pages back to the full user page', function (): void {
        givePeoplePageHistory($this->customer, $this->admin, 12);
        actingAs($this->admin);

        $this->get(route('admin.users.tab', [$this->customer, 'ads']))
            ->assertOk()
            ->assertSee(route('admin.users.show', $this->customer) . '?tab=ads&amp;page=2', false);
        $this->get(route('admin.users.show', [$this->customer, 'tab' => 'ads', 'page' => 2]))
            ->assertOk()
            ->assertSee('إعلان رقم 01')
            ->assertSee('إعلان رقم 02')
            ->assertDontSee('إعلان رقم 12');
    });

    it('keeps the query count flat with 1 or 25 ads, orders and audit rows on every tab', function (string $tab, int $budget): void {
        actingAs($this->admin);
        $uri = route('admin.users.show', [$this->customer, 'tab' => $tab]);

        givePeoplePageHistory($this->customer, $this->admin, 1);
        $few = $this->steadyQueryCount($uri);

        givePeoplePageHistory($this->customer, $this->admin, 24);
        $many = $this->steadyQueryCount($uri);

        expect($many)->toBe($few)->toBeLessThanOrEqual($budget);
    })->with([
        'profile' => ['profile', 14],
        'ads' => ['ads', 14],
        // two bounded id reads (one per side), their two counts, the rows and the four eager loads
        'orders' => ['orders', 16],
        'wallet' => ['wallet', 14],
    ]);

    it('keeps each lazily loaded panel flat with 1 or 25 rows', function (string $tab): void {
        actingAs($this->admin);
        $uri = route('admin.users.tab', [$this->customer, $tab]);

        givePeoplePageHistory($this->customer, $this->admin, 1);
        $few = $this->steadyQueryCount($uri);

        givePeoplePageHistory($this->customer, $this->admin, 24);

        expect($this->steadyQueryCount($uri))->toBe($few);
    })->with(['ads', 'orders']);

    it('bounds the audit trail and shows it only to staff who can read the activity log', function (): void {
        givePeoplePageHistory($this->customer, $this->admin, 12);

        actingAs($this->admin);
        $html = $this->get(route('admin.users.show', $this->customer))->assertOk()->assertSee('سجل التدقيق')->getContent();
        expect(substr_count($html, 'إيقاف الحساب'))->toBe(8);

        actingAs(User::factory()->create()->assignRole('moderator'));
        $this->get(route('admin.users.show', $this->customer))->assertOk()->assertDontSee('سجل التدقيق');
    });

    it('keeps the moderation, roles and impersonation rules', function (): void {
        actingAs($this->admin);
        $this->get(route('admin.users.show', $this->customer))
            ->assertSee(route('admin.users.suspend', $this->customer), false)
            ->assertSee(route('admin.users.roles', $this->customer), false)
            ->assertSee(route('admin.users.reset-password', $this->customer), false)
            ->assertSee(route('admin.users.impersonate', $this->customer), false);

        $this->customer->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->get(route('admin.users.show', $this->customer))
            ->assertSee(route('admin.users.activate', $this->customer), false)
            ->assertDontSee(route('admin.users.suspend', $this->customer), false);

        $moderator = User::factory()->create()->assignRole('moderator');
        $this->get(route('admin.users.show', $moderator))
            ->assertOk()
            ->assertDontSee(route('admin.users.impersonate', $moderator), false);

        actingAs(User::factory()->create()->assignRole('support'));
        $this->get(route('admin.users.show', $this->customer))
            ->assertOk()
            ->assertDontSee(route('admin.users.activate', $this->customer), false)
            ->assertDontSee(route('admin.users.roles', $this->customer), false)
            ->assertDontSee(route('admin.users.reset-password', $this->customer), false)
            ->assertDontSee(route('admin.users.impersonate', $this->customer), false);
    });

    it('links the statement only for finance staff', function (): void {
        actingAs($this->admin);
        $this->get(route('admin.users.show', $this->customer))->assertSee(route('admin.finance.statements.show', $this->customer), false);

        actingAs(User::factory()->create()->assignRole('moderator'));
        $this->get(route('admin.users.show', $this->customer))->assertDontSee(route('admin.finance.statements.show', $this->customer), false);
    });
});

describe('profile.edit', function (): void {
    it('renders the form-width shell with the Save button in the header and the password section', function (): void {
        actingAs($this->admin);

        $html = $this->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('mx-auto w-full max-w-6xl', false)
            ->assertSee('action="' . route('admin.profile.update') . '"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('حفظ التغييرات')
            ->assertSee('تغيير كلمة المرور')
            ->assertSee('id="password"', false)
            ->assertSee('id="password_confirmation"', false)
            ->assertSee('مدير عام')
            ->getContent();

        expect(substr_count($html, '<h1'))->toBe(1);
    });

    it('shows the validation summary linking each error to its field', function (): void {
        actingAs($this->admin);

        $this->from(route('admin.profile.edit'))
            ->followingRedirects()
            ->put(route('admin.profile.update'), [
                'full_name' => '',
                'email' => 'not-an-email',
                'password' => 'a-new-strong-pass',
                'password_confirmation' => 'something-else',
            ])
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('data-qb-autofocus', false)
            ->assertSee('href="#full_name"', false)
            ->assertSee('href="#email"', false)
            ->assertSee('href="#password"', false);
    });

    it('asks for a new password up front when one is required', function (): void {
        $this->admin->forceFill(['must_change_password' => true])->save();
        actingAs($this->admin);

        $this->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee(__('admin.auth.password_change_required'))
            ->assertSee('مطلوبة: اختر كلمة مرور جديدة');
    });

    it('stays within a fixed query budget', function (): void {
        actingAs($this->admin);

        expect($this->steadyQueryCount(route('admin.profile.edit')))->toBeLessThanOrEqual(10);
    });
});
