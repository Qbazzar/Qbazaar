<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\DatabaseNotification;
use App\Models\RecentView;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\seed;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function refreshTokenExpiring(User $user, Carbon $expiresAt): RefreshToken
{
    return RefreshToken::query()->create([
        'user_id' => $user->id,
        'token_hash' => Str::random(40),
        'expires_at' => $expiresAt,
    ]);
}

function storedNotification(User $user, ?Carbon $readAt): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => [],
        'read_at' => $readAt,
    ]);
}

it('prunes refresh tokens a day past expiry and keeps live ones', function (): void {
    $user = User::factory()->create();
    refreshTokenExpiring($user, Carbon::now()->subHours(25));
    $recentlyExpired = refreshTokenExpiring($user, Carbon::now()->subHour());
    $live = refreshTokenExpiring($user, Carbon::now()->addDays(10));

    expect(Artisan::call('model:prune', ['--model' => [RefreshToken::class]]))->toBe(0);

    expect(RefreshToken::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$recentlyExpired->id, $live->id])->sort()->values()->all());
});

it('prunes old guest views but never a signed-in history', function (): void {
    seed([CategorySeeder::class, LocationSeeder::class]);
    $user = User::factory()->create();
    $ad = Ad::factory()->create();

    $oldGuest = RecentView::query()->create(['session_id' => 'guest-a', 'ad_id' => $ad->id, 'viewed_at' => Carbon::now()->subDays(31)]);
    $freshGuest = RecentView::query()->create(['session_id' => 'guest-b', 'ad_id' => $ad->id, 'viewed_at' => Carbon::now()->subDays(2)]);
    $oldMember = RecentView::query()->create(['user_id' => $user->id, 'ad_id' => $ad->id, 'viewed_at' => Carbon::now()->subDays(90)]);

    expect(Artisan::call('model:prune', ['--model' => [RecentView::class]]))->toBe(0);

    expect(RecentView::query()->find($oldGuest->id))->toBeNull()
        ->and(RecentView::query()->find($freshGuest->id))->not->toBeNull()
        ->and(RecentView::query()->find($oldMember->id))->not->toBeNull();
});

it('prunes notifications read long ago and keeps unread ones', function (): void {
    $user = User::factory()->create();
    $oldRead = storedNotification($user, Carbon::now()->subDays(91));
    $recentRead = storedNotification($user, Carbon::now()->subDays(5));
    $unread = storedNotification($user, null);
    DB::table('notifications')->where('id', $unread->id)->update(['created_at' => Carbon::now()->subYears(2)]);

    expect(Artisan::call('model:prune', ['--model' => [DatabaseNotification::class]]))->toBe(0);

    expect(DatabaseNotification::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$recentRead->id, $unread->id])->sort()->values()->all())
        ->and(DatabaseNotification::query()->find($oldRead->id))->toBeNull();
});

it('cleans activity older than the retention in batches', function (): void {
    config(['qbazaar.retention.prune_chunk' => 2, 'activitylog.clean_after_days' => 30]);

    foreach (range(1, 5) as $i) {
        activity('test')->log("old {$i}");
    }
    Activity::query()->update(['created_at' => Carbon::now()->subDays(31)]);
    activity('test')->log('recent');

    expect(Artisan::call('activitylog:clean', ['--force' => true]))->toBe(0);

    expect(Activity::query()->pluck('description')->all())->toBe(['recent']);
});

it('schedules every retention task', function (string $name, string $command): void {
    // The schedule is only built once the console application starts.
    Artisan::call('schedule:list');

    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => $event->description === $name);

    expect($event?->command)->toContain($command);
})->with([
    ['auth.prune-access-tokens', 'sanctum:prune-expired'],
    ['auth.prune-refresh-tokens', 'model:prune'],
    ['retention.prune-history', 'model:prune'],
    ['retention.clean-activity-log', 'activitylog:clean'],
    ['retention.prune-failed-jobs', 'queue:prune-failed'],
    ['auth.clear-password-resets', 'auth:clear-resets'],
    ['horizon.snapshot', 'horizon:snapshot'],
]);
