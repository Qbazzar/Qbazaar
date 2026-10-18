<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\BusinessApplicationStatus;
use App\Models\BusinessApplication;
use App\Models\User;
use App\Services\Business\BusinessApplicationQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesBusinessApplications;

uses(RefreshDatabase::class, CreatesBusinessApplications::class);

beforeEach(function (): void {
    Storage::fake('local');
    config(['qbazaar.business.documents_disk' => 'local']);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

/**
 * @return array<string, mixed>
 */
function businessApplicationPayload(array $overrides = []): array
{
    return [
        'business_name' => 'Doha Electronics',
        'legal_name' => 'Doha Electronics Trading W.L.L.',
        'commercial_registration_number' => 'cr-12345',
        'applicant_note' => 'We sell phones and laptops.',
        'documents' => [test()->pdfDocument()],
        ...$overrides,
    ];
}

it('returns null before the user ever applied', function (): void {
    getJson('/api/v1/account/business-application')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('submits an application with its documents kept private', function (): void {
    Cache::put(BusinessApplicationQueue::PENDING_COUNT_KEY, 0);

    $response = postJson('/api/v1/account/business-application', businessApplicationPayload([
        'business_name' => '<b>Doha Electronics</b>',
    ]))->assertCreated();

    $response
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.business_name', 'Doha Electronics')
        ->assertJsonPath('data.commercial_registration_number', 'CR-12345')
        ->assertJsonPath('data.can_resubmit', false)
        ->assertJsonCount(1, 'data.documents')
        ->assertJsonPath('data.documents.0.name', 'commercial-registration')
        ->assertJsonPath('data.documents.0.mime_type', 'application/pdf');

    expect($response->json('data.documents.0'))->not->toHaveKeys(['url', 'path', 'file_name']);

    $application = BusinessApplication::query()->sole();
    $document = $application->documents()->sole();

    expect($application->user_id)->toBe($this->user->id)
        ->and($application->open_user_id)->toBe($this->user->id)
        ->and($application->status)->toBe(BusinessApplicationStatus::PENDING)
        ->and($document->disk)->toBe('local')
        ->and(Storage::disk('local')->exists($document->getPathRelativeToRoot()))->toBeTrue()
        ->and(Cache::has(BusinessApplicationQueue::PENDING_COUNT_KEY))->toBeFalse()
        ->and(Activity::query()->where('event', 'business_application_submitted')->where('causer_id', $this->user->id)->exists())->toBeTrue();

    getJson('/api/v1/account/business-application')
        ->assertOk()
        ->assertJsonPath('data.id', $application->id)
        ->assertJsonCount(1, 'data.documents');
});

it('needs at least one document for a first application', function (): void {
    postJson('/api/v1/account/business-application', businessApplicationPayload(['documents' => []]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['documents'], 'error.details');

    expect(BusinessApplication::query()->count())->toBe(0);
});

it('validates the declared details and the document types', function (): void {
    postJson('/api/v1/account/business-application', [
        'business_name' => 'A',
        'legal_name' => '',
        'commercial_registration_number' => 'CR 123; DROP',
        'documents' => [UploadedFile::fake()->create('notes.txt', 5, 'text/plain')],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['business_name', 'legal_name', 'commercial_registration_number', 'documents.0'], 'error.details');

    postJson('/api/v1/account/business-application', businessApplicationPayload([
        'documents' => array_fill(0, 6, UploadedFile::fake()->create('cr.pdf', 10, 'application/pdf')),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['documents'], 'error.details');
});

it('allows one open application per user', function (): void {
    postJson('/api/v1/account/business-application', businessApplicationPayload())->assertCreated();

    postJson('/api/v1/account/business-application', businessApplicationPayload())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'BIZ_002');

    expect(BusinessApplication::query()->count())->toBe(1);
});

it('lets the applicant answer a request for more information on the same application', function (): void {
    $application = BusinessApplication::factory()->needsInfo()->create(['user_id' => $this->user->id]);

    postJson('/api/v1/account/business-application', businessApplicationPayload([
        'legal_name' => 'Doha Electronics Trading Company W.L.L.',
        'documents' => [test()->pdfDocument('trade-licence.pdf')],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.id', $application->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.legal_name', 'Doha Electronics Trading Company W.L.L.')
        ->assertJsonCount(1, 'data.documents');

    expect(BusinessApplication::query()->count())->toBe(1)
        ->and($application->fresh()?->submitted_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'business_application_resubmitted')->exists())->toBeTrue();
});

it('lets a needs-info answer correct the details without new documents', function (): void {
    $application = BusinessApplication::factory()->needsInfo()->create(['user_id' => $this->user->id]);

    postJson('/api/v1/account/business-application', businessApplicationPayload(['documents' => []]))
        ->assertCreated()
        ->assertJsonPath('data.id', $application->id)
        ->assertJsonPath('data.status', 'pending');
});

it('accepts a new application once the previous one was rejected', function (): void {
    $rejected = BusinessApplication::factory()->rejected()->create(['user_id' => $this->user->id]);

    $response = postJson('/api/v1/account/business-application', businessApplicationPayload())->assertCreated();

    expect($response->json('data.id'))->not->toBe($rejected->id)
        ->and(BusinessApplication::query()->where('user_id', $this->user->id)->count())->toBe(2);
});

it('refuses an application from an already verified business', function (): void {
    $this->user->forceFill(['account_type' => AccountType::BUSINESS, 'business_verified_at' => now()])->save();

    postJson('/api/v1/account/business-application', businessApplicationPayload())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'BIZ_004');
});

it('reports the verified badge in the verification status', function (): void {
    $this->user->forceFill(['account_type' => AccountType::BUSINESS, 'business_verified_at' => now()])->save();

    getJson('/api/v1/account/verification-status')
        ->assertOk()
        ->assertJsonPath('data.business_verified', true);
});

it('rejects guests', function (): void {
    $this->refreshApplication();

    getJson('/api/v1/account/business-application')->assertUnauthorized();
    postJson('/api/v1/account/business-application', [])->assertUnauthorized();
});
