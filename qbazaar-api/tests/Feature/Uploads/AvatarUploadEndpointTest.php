<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // MediaLibrary stores files on the default disk. Faking it keeps the
    // filesystem clean between tests and lets us assert later if needed.
    Storage::fake('public');
    Storage::fake('local');

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

it('stores an avatar and returns conversion URLs', function (): void {
    $file = UploadedFile::fake()->image('avatar.png', 600, 600);

    $response = postJson('/api/v1/uploads/avatar', [
        'avatar' => $file,
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->has('data.avatar_url')
                ->has('data.avatar_thumb_url')
                ->has('data.avatar_medium_url')
                ->etc(),
        );

    expect($this->user->fresh()->getMedia('avatar'))->toHaveCount(1);
});

it('rejects oversized uploads with 422', function (): void {
    // 6 MB exceeds the 5 MB cap.
    $file = UploadedFile::fake()->image('big.png')->size(6 * 1024);

    postJson('/api/v1/uploads/avatar', [
        'avatar' => $file,
    ], [
        'Accept' => 'application/json',
    ])->assertStatus(422);
});

it('rejects non-image uploads with 422', function (): void {
    $file = UploadedFile::fake()->create('not-an-image.txt', 100, 'text/plain');

    postJson('/api/v1/uploads/avatar', [
        'avatar' => $file,
    ], [
        'Accept' => 'application/json',
    ])->assertStatus(422);
});

it('rejects unauthenticated requests', function (): void {
    $this->refreshApplication();
    Storage::fake('public');
    Storage::fake('local');

    $file = UploadedFile::fake()->image('avatar.png', 600, 600);

    postJson('/api/v1/uploads/avatar', [
        'avatar' => $file,
    ], [
        'Accept' => 'application/json',
    ])->assertStatus(401);
});
