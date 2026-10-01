<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\Ads\CreateAdRequest;
use App\Http\Requests\Api\V1\Reviews\CreateReviewRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('answers NOT_FOUND for an unknown API route', function (): void {
    getJson('/api/v1/no-such-endpoint')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND')
        ->assertJsonPath('error.message_key', 'errors.not.found');
});

it('answers with the bound model\'s own code when a record is missing', function (): void {
    getJson('/api/v1/users/' . Str::ulid() . '/public-profile')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'USER_001');

    getJson('/api/v1/ads/' . Str::ulid())
        ->assertNotFound()
        ->assertJsonPath('error.code', 'AD_001');
});

it('answers FORBIDDEN from the enum when a policy refuses', function (): void {
    Route::middleware('api')->get('/api/v1/test-forbidden', fn () => throw new AuthorizationException);

    getJson('/api/v1/test-forbidden')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN')
        ->assertJsonPath('error.message_key', 'errors.forbidden');
});

it('reads ad and review limits from config', function (): void {
    config(['qbazaar.ads.title_max_length' => 10, 'qbazaar.reviews.comment_max_length' => 5]);

    $titleRules = (new CreateAdRequest)->rules()['title'];
    $commentRules = (new CreateReviewRequest)->rules()['comment'];

    expect(Validator::make(['title' => 'Eleven char'], ['title' => $titleRules])->fails())->toBeTrue()
        ->and(Validator::make(['title' => 'Ten chars!'], ['title' => $titleRules])->passes())->toBeTrue()
        ->and(Validator::make(['comment' => 'too long'], ['comment' => $commentRules])->fails())->toBeTrue();
});
