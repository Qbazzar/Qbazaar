<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\ReportCategory;
use App\Enums\ReportTarget;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

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
    $this->seller = User::factory()->create(['full_name' => 'محمد الكعبي']);
    $this->ad = $this->makeAd($this->seller, ['title' => 'آيفون 14 برو ماكس', 'status' => AdStatus::ACTIVE->value]);
    $this->reporter = User::factory()->create(['full_name' => 'سارة المُبلِّغة', 'email' => 'sara@example.com']);
    $this->report = Report::factory()->create([
        'reporter_id' => $this->reporter->id,
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::FRAUD->value,
        'description' => 'السعر غير حقيقي والبائع يطلب تحويلاً مسبقاً.',
    ]);
});

/** Files $count more reports against the same ad, each by a reporter with a photo. */
function fileMoreReportsAgainst(Report $report, int $count): void
{
    foreach (range(1, $count) as $i) {
        $reporter = User::factory()->create(['full_name' => sprintf('مُبلِّغ رقم %02d', $i)]);
        $reporter->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('avatar');

        Report::factory()->create([
            'reporter_id' => $reporter->id,
            'target_type' => $report->target_type->value,
            'target_id' => $report->target_id,
            'created_at' => now()->subMinutes($i),
        ]);

        activity('admin')->performedOn($report)->causedBy($reporter)
            ->event('admin.reports.resolve')->log('admin.reports.resolve');
    }
}

function reportPageQueryCount(string $uri): int
{
    test()->get($uri)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($uri)->assertOk();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

it('renders in the page shell for every role that can open reports, with the actions each may use', function (string $role, array $visible, array $hidden): void {
    actingAs(User::factory()->create()->assignRole($role));

    $html = $this->get(route('admin.reports.show', $this->report))
        ->assertOk()
        ->assertSee('data-qb-page', false)
        ->assertSee('السعر غير حقيقي والبائع يطلب تحويلاً مسبقاً.')
        ->assertSee('آيفون 14 برو ماكس')
        ->assertSee('سارة المُبلِّغة')
        ->assertSee(__('admin.report.filed'))
        ->assertDontSee(__('admin.shell.widget_failed'))
        ->getContent();

    expect(substr_count($html, '<h1'))->toBe(1);
    foreach ($visible as $needle) {
        expect($html)->toContain($needle);
    }
    foreach ($hidden as $needle) {
        expect($html)->not->toContain($needle);
    }
})->with([
    'super_admin' => ['super_admin', ['id="report-moderation-title"', '/resolve"', '/suspend-ad"'], []],
    'moderator' => ['moderator', ['id="report-moderation-title"', '/resolve"', '/suspend-ad"'], []],
    'support' => ['support', ['id="report-reporter-title"', 'id="report-history-title"'], ['id="report-moderation-title"', '/resolve"', '/suspend-ad"']],
]);

it('previews each kind of reported record', function (ReportTarget $type, Closure $makeTarget, string $expected): void {
    actingAs($this->admin);
    $target = $makeTarget($this->seller, $this->ad);
    $report = Report::factory()->create(['target_type' => $type->value, 'target_id' => $target->getKey()]);

    $this->get(route('admin.reports.show', $report))
        ->assertOk()
        ->assertSee($expected)
        ->assertDontSee(__('admin.shell.widget_failed'))
        ->assertDontSee('العنصر المُبلَّغ عنه لم يعد موجوداً.');
})->with([
    'user' => [ReportTarget::USER, fn (User $seller) => $seller, 'محمد الكعبي'],
    'conversation' => [ReportTarget::CONVERSATION, fn (User $seller, $ad) => Conversation::factory()->create(['ad_id' => $ad->id, 'seller_id' => $seller->id]), 'آيفون 14 برو ماكس'],
    'message' => [ReportTarget::MESSAGE, fn (User $seller) => Message::factory()->create(['sender_id' => $seller->id, 'body' => 'حوّل المبلغ قبل اللقاء']), 'حوّل المبلغ قبل اللقاء'],
]);

it('says so when the reported record is gone', function (): void {
    actingAs($this->admin);
    $report = Report::factory()->create(['target_type' => ReportTarget::USER->value, 'target_id' => strtolower((string) str()->ulid())]);

    $this->get(route('admin.reports.show', $report))
        ->assertOk()
        ->assertSee('العنصر المُبلَّغ عنه لم يعد موجوداً.');
});

it('hides the moderation actions once the report is closed and shows who closed it', function (): void {
    actingAs($this->admin);

    $this->post(route('admin.reports.dismiss', $this->report))->assertRedirect();

    $html = $this->get(route('admin.reports.show', $this->report))
        ->assertOk()
        ->assertDontSee('id="report-moderation-title"', false)
        ->assertSee('id="report-reviewer-title"', false)
        ->getContent();
    $history = (string) str($html)->after('id="report-history-title"')->before('</section>');

    expect($history)->toContain('تجاهل البلاغ')
        ->toContain('أحمد المشرف')
        ->toContain(__('admin.report.filed'))
        ->toContain('سارة المُبلِّغة');
});

it('lists the five newest other reports on the same record with a link to the rest', function (): void {
    actingAs($this->admin);
    fileMoreReportsAgainst($this->report, 7);

    $html = $this->get(route('admin.reports.show', $this->report))
        ->assertOk()
        ->assertSee('عرض الكل (7)')
        ->assertSee(route('admin.reports.index', ['q' => $this->ad->id]), false)
        ->getContent();
    $others = (string) str($html)->after('id="other-reports-title"')->before('</section>');

    expect(substr_count($others, '>مُبلِّغ رقم '))->toBe(5)
        ->and($others)->not->toContain(route('admin.reports.show', $this->report) . '"');
});

it('lists the validation errors of a failed action above the page', function (): void {
    actingAs($this->admin);
    $show = route('admin.reports.show', $this->report);

    $this->from($show)
        ->followingRedirects()
        ->post(route('admin.reports.action', $this->report), ['admin_notes' => ''])
        ->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('data-qb-autofocus', false)
        ->assertSee('href="#admin_notes"', false);
});

it('stays within a fixed query budget whatever the number of other reports and log rows', function (): void {
    actingAs($this->admin);
    $uri = route('admin.reports.show', $this->report);

    fileMoreReportsAgainst($this->report, 1);
    $withOne = reportPageQueryCount($uri);

    fileMoreReportsAgainst($this->report, 24);
    $withTwentyFive = reportPageQueryCount($uri);

    expect($withTwentyFive)->toBe($withOne)
        ->and($withOne)->toBeLessThanOrEqual(15);
});
