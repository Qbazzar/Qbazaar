<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\BusinessApplicationStatus;
use App\Enums\BusinessNotice;
use App\Models\BusinessApplication;
use App\Models\User;
use App\Notifications\Business\BusinessAccountNotification;
use App\Services\Business\BusinessApplicationQueue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesBusinessApplications;

uses(RefreshDatabase::class, CountsPageQueries::class, CreatesBusinessApplications::class);

beforeEach(function (): void {
    $this->withoutVite();
    Notification::fake();
    Storage::fake('local');
    Cache::flush();
    config(['qbazaar.business.documents_disk' => 'local']);
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->staff = [
        'super_admin' => User::factory()->create()->assignRole('super_admin'),
        'moderator' => User::factory()->create()->assignRole('moderator'),
        'support' => User::factory()->create()->assignRole('support'),
    ];
    $this->admin = $this->staff['super_admin'];
    $this->applicant = User::factory()->create(['full_name' => 'Khalid Applicant']);
});

describe('the review queue', function (): void {
    it('serves the oldest waiting application first to every staff role', function (string $role): void {
        $this->applicationWithDocument(['business_name' => 'Newer Shop', 'submitted_at' => now()->subHour()]);
        $this->applicationWithDocument(['business_name' => 'Older Shop', 'submitted_at' => now()->subDay()]);
        BusinessApplication::factory()->approved()->create(['business_name' => 'Decided Shop']);

        actingAs($this->staff[$role]);

        $this->get(route('admin.business-applications.index'))
            ->assertOk()
            ->assertSeeInOrder(['Older Shop', 'Newer Shop'])
            ->assertDontSee('Decided Shop');
    })->with(['super_admin', 'moderator', 'support']);

    it('filters by status and searches by the CR number', function (): void {
        BusinessApplication::factory()->rejected()->create(['business_name' => 'Rejected Shop', 'commercial_registration_number' => 'CR-777']);
        BusinessApplication::factory()->rejected()->create(['business_name' => 'Other Rejected']);
        actingAs($this->admin);

        $this->get(route('admin.business-applications.index', ['status' => 'rejected', 'q' => 'CR-777']))
            ->assertOk()
            ->assertSee('Rejected Shop')
            ->assertDontSee('Other Rejected');

        $this->get(route('admin.business-applications.index', ['status' => 'everything']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    });

    it('keeps the queue query count flat as applications pile up', function (): void {
        actingAs($this->admin);
        $uri = route('admin.business-applications.index');

        foreach (range(1, 2) as $i) {
            $this->applicationWithDocument(['submitted_at' => now()->subMinutes($i)]);
        }
        $few = $this->steadyQueryCount($uri);

        foreach (range(1, 15) as $i) {
            $this->applicationWithDocument(['submitted_at' => now()->subHours($i), 'user_id' => User::factory()->create()->id]);
        }
        $many = $this->steadyQueryCount($uri);

        expect($many)->toBe($few)->toBeLessThanOrEqual(14);
    });

    it('serves the queue from its index', function (): void {
        expect(Schema::hasIndex('business_applications', 'business_applications_queue_idx'))->toBeTrue()
            ->and(Schema::hasIndex('business_applications', 'business_applications_user_idx'))->toBeTrue()
            ->and(Schema::hasIndex('business_applications', 'business_applications_open_user_unique'))->toBeTrue();
    });

    it('counts waiting applications on the sidebar badge for staff who can view them', function (): void {
        $this->applicationWithDocument();
        actingAs($this->staff['support']);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.business-applications.index'), false)
            ->assertSee(__('admin.business_applications.pending_badge'));
    });
});

describe('the application page', function (): void {
    it('shows the details and documents, with the decision only for reviewers', function (): void {
        $application = $this->applicationWithDocument([
            'user_id' => $this->applicant->id,
            'business_name' => 'Doha Electronics',
            'commercial_registration_number' => 'CR-12345',
            'applicant_note' => 'We sell phones.',
        ]);
        $documentUrl = route('admin.business-applications.document', [$application, $application->documents()->sole()->id]);

        actingAs($this->admin);
        $this->get(route('admin.business-applications.show', $application))
            ->assertOk()
            ->assertSee('Doha Electronics')
            ->assertSee('CR-12345')
            ->assertSee('We sell phones.')
            ->assertSee('Khalid Applicant')
            ->assertSee($documentUrl, false)
            ->assertSee(route('admin.business-applications.approve', $application), false);

        actingAs($this->staff['support']);
        $this->get(route('admin.business-applications.show', $application))
            ->assertOk()
            ->assertSee('commercial-registration')
            ->assertDontSee($documentUrl, false)
            ->assertDontSee(route('admin.business-applications.approve', $application), false);
    });

    it('streams a document to reviewers only, sandboxed and never cached', function (): void {
        $application = $this->applicationWithDocument();
        $document = $application->documents()->sole();
        $url = route('admin.business-applications.document', [$application, $document->id]);

        actingAs($this->admin);
        $response = $this->get($url)->assertOk();

        expect($response->headers->get('Content-Security-Policy'))->toBe('sandbox')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

        actingAs($this->staff['support']);
        $this->get($url)->assertForbidden();
    });

    it('never opens another application\'s document through this one', function (): void {
        $application = $this->applicationWithDocument();
        $other = $this->applicationWithDocument();
        actingAs($this->admin);

        $this->get(route('admin.business-applications.document', [$application, $other->documents()->sole()->id]))->assertNotFound();
    });
});

describe('decisions', function (): void {
    it('approves an application: the account becomes a verified business with the declared details', function (): void {
        $application = $this->applicationWithDocument([
            'user_id' => $this->applicant->id,
            'business_name' => 'Doha Electronics',
            'legal_name' => 'Doha Electronics Trading W.L.L.',
            'commercial_registration_number' => 'CR-12345',
        ]);
        Cache::put(BusinessApplicationQueue::PENDING_COUNT_KEY, 1);
        actingAs($this->admin);

        $this->post(route('admin.business-applications.approve', $application), ['note' => 'Welcome aboard'])
            ->assertRedirect()
            ->assertSessionHas('status', __('admin.business_applications.flash.approved'));

        $applicant = $this->applicant->fresh();
        $application->refresh();

        expect($applicant?->account_type)->toBe(AccountType::BUSINESS)
            ->and($applicant?->isVerifiedBusiness())->toBeTrue()
            ->and($applicant?->business_verified_by)->toBe($this->admin->id)
            ->and($applicant?->businessProfile?->business_name)->toBe('Doha Electronics')
            ->and($applicant?->businessProfile?->legal_name)->toBe('Doha Electronics Trading W.L.L.')
            ->and($applicant?->businessProfile?->commercial_registration_number)->toBe('CR-12345')
            ->and($application->status)->toBe(BusinessApplicationStatus::APPROVED)
            ->and($application->open_user_id)->toBeNull()
            ->and($application->reviewed_by)->toBe($this->admin->id)
            ->and($application->review_note)->toBe('Welcome aboard')
            ->and(Cache::has(BusinessApplicationQueue::PENDING_COUNT_KEY))->toBeFalse();

        $audit = Activity::query()->where('event', 'admin.business-applications.approve')->sole();
        expect($audit->causer_id)->toBe($this->admin->id)
            ->and($audit->subject_id)->toBe($application->id)
            ->and(Activity::query()->where('log_name', 'admin')->count())->toBe(1);

        Notification::assertSentTo($this->applicant, BusinessAccountNotification::class, fn (BusinessAccountNotification $notification): bool => $notification->notice === BusinessNotice::APPLICATION_APPROVED
            && $notification->applicationId === $application->id);

        $this->get(route('admin.business-applications.show', $application))
            ->assertOk()
            ->assertSee(__('admin.timeline.events.business-applications.approve'));
    });

    it('keeps the business name the company already uses', function (): void {
        $company = User::factory()->business()->create();
        $company->businessProfile()->create(['business_name' => 'Existing Name']);
        $application = $this->applicationWithDocument(['user_id' => $company->id, 'business_name' => 'Declared Name']);
        actingAs($this->admin);

        $this->post(route('admin.business-applications.approve', $application))->assertRedirect();

        expect($company->fresh()?->businessProfile?->business_name)->toBe('Existing Name')
            ->and($company->fresh()?->isVerifiedBusiness())->toBeTrue();
    });

    it('rejects with a required reason the applicant reads', function (): void {
        $application = $this->applicationWithDocument(['user_id' => $this->applicant->id]);
        actingAs($this->staff['moderator']);

        $this->post(route('admin.business-applications.reject', $application), ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->post(route('admin.business-applications.reject', $application), ['note' => 'no'])
            ->assertSessionHasErrors('note');

        $this->post(route('admin.business-applications.reject', $application), ['note' => '<b>The CR number does not match</b>'])
            ->assertRedirect()
            ->assertSessionHas('status', __('admin.business_applications.flash.rejected'));

        $application->refresh();
        expect($application->status)->toBe(BusinessApplicationStatus::REJECTED)
            ->and($application->review_note)->toBe('The CR number does not match')
            ->and($this->applicant->fresh()?->isVerifiedBusiness())->toBeFalse()
            ->and(Activity::query()->where('event', 'admin.business-applications.reject')->exists())->toBeTrue();

        Notification::assertSentTo($this->applicant, BusinessAccountNotification::class, fn (BusinessAccountNotification $notification): bool => $notification->notice === BusinessNotice::APPLICATION_REJECTED
            && $notification->reason === 'The CR number does not match');
    });

    it('asks the applicant for more information', function (): void {
        $application = $this->applicationWithDocument(['user_id' => $this->applicant->id]);
        actingAs($this->admin);

        $this->post(route('admin.business-applications.request-info', $application), ['note' => 'Please attach the trade licence.'])
            ->assertRedirect()
            ->assertSessionHas('status', __('admin.business_applications.flash.info_requested'));

        $application->refresh();
        expect($application->status)->toBe(BusinessApplicationStatus::NEEDS_INFO)
            ->and($application->open_user_id)->toBe($this->applicant->id)
            ->and($application->review_note)->toBe('Please attach the trade licence.');

        Notification::assertSentTo($this->applicant, BusinessAccountNotification::class, fn (BusinessAccountNotification $notification): bool => $notification->notice === BusinessNotice::APPLICATION_NEEDS_INFO);

        $data = (new BusinessAccountNotification(BusinessNotice::APPLICATION_NEEDS_INFO, 'Please attach the trade licence.', $application->id))->toArray($this->applicant);
        expect($data['category'])->toBe('business.application_needs_info')
            ->and($data['body'])->toContain('Please attach the trade licence.')
            ->and($data['record_id'])->toBe($application->id);
    });

    it('never decides an application twice', function (): void {
        $application = BusinessApplication::factory()->rejected()->create(['user_id' => $this->applicant->id]);
        actingAs($this->admin);

        $this->post(route('admin.business-applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('error');

        expect($application->fresh()?->status)->toBe(BusinessApplicationStatus::REJECTED)
            ->and($this->applicant->fresh()?->isVerifiedBusiness())->toBeFalse();
        Notification::assertNothingSent();
    });

    it('lets only staff who verify companies decide', function (string $role, bool $allowed): void {
        $application = $this->applicationWithDocument(['user_id' => $this->applicant->id]);
        actingAs($this->staff[$role]);

        foreach (['approve' => [], 'reject' => ['note' => 'Not a real business'], 'request-info' => ['note' => 'Send the licence']] as $action => $payload) {
            $status = $this->post(route('admin.business-applications.' . $action, $application), $payload)->getStatusCode();

            expect($status === 403)->toBe(! $allowed);
        }
    })->with([
        'super_admin' => ['super_admin', true],
        'moderator' => ['moderator', true],
        'support' => ['support', false],
    ]);
});
