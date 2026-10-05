<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\DisputeResolution;
use App\Enums\Fulfillment;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PromotionStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\ReportStatus;
use App\Enums\SettlementStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Services\Ledger\LedgerReconciler;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\DemoOptions;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;
use function Pest\Laravel\seed;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    // Photo conversions go to a queue nobody works in the test, as they would
    // on a dev box without a worker; everything else still runs inline.
    config(['queue.default' => 'database']);

    seed(DatabaseSeeder::REFERENCE_SEEDERS);
});

/**
 * @param array<string, mixed> $options
 */
function demoCommand(array $options): PendingCommand
{
    $command = artisan('qbazaar:demo', $options);
    assert($command instanceof PendingCommand);

    return $command;
}

/**
 * @param class-string<BackedEnum> $enum
 * @param list<string> $except
 * @return list<string>
 */
function demoStatuses(string $enum, array $except = []): array
{
    $values = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

    return array_values(array_diff($values, $except));
}

/**
 * @return list<string>
 */
function demoValuesIn(string $table, string $column): array
{
    return array_values(DB::table($table)->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->map(static fn (mixed $value): string => (string) $value)->all());
}

it('builds a demo marketplace with every state of every entity and a balanced ledger', function (): void {
    demoCommand(['--users' => 10, '--ads' => 40])->assertSuccessful();

    expect(demoValuesIn('ads', 'status'))->toEqualCanonicalizing(demoStatuses(AdStatus::class))
        ->and(demoValuesIn('offers', 'status'))->toEqualCanonicalizing(demoStatuses(OfferStatus::class))
        ->and(demoValuesIn('orders', 'status'))->toEqualCanonicalizing(demoStatuses(OrderStatus::class))
        ->and(demoValuesIn('orders', 'fulfillment'))->toEqualCanonicalizing(demoStatuses(Fulfillment::class))
        ->and(demoValuesIn('orders', 'dispute_resolution'))->toEqualCanonicalizing(demoStatuses(DisputeResolution::class))
        ->and(demoValuesIn('purchase_requests', 'status'))->toEqualCanonicalizing(demoStatuses(PurchaseRequestStatus::class))
        ->and(demoValuesIn('commission_settlements', 'status'))->toEqualCanonicalizing(demoStatuses(SettlementStatus::class))
        ->and(demoValuesIn('commission_settlements', 'method'))->toEqualCanonicalizing(['bank_transfer', 'wallet'])
        ->and(demoValuesIn('withdrawals', 'status'))->toEqualCanonicalizing(demoStatuses(WithdrawalStatus::class))
        ->and(demoValuesIn('ad_promotions', 'status'))->toEqualCanonicalizing(demoStatuses(PromotionStatus::class))
        ->and(demoValuesIn('reports', 'status'))->toEqualCanonicalizing(demoStatuses(ReportStatus::class))
        ->and(demoValuesIn('support_tickets', 'status'))->toEqualCanonicalizing(demoStatuses(SupportTicketStatus::class));

    expect(DB::table('user_blocks')->exists())->toBeTrue()
        ->and(DB::table('business_profiles')->exists())->toBeTrue()
        ->and(DB::table('follows')->exists())->toBeTrue()
        ->and(DB::table('favorites')->exists())->toBeTrue()
        ->and(DB::table('recently_viewed')->exists())->toBeTrue()
        ->and(DB::table('saved_searches')->exists())->toBeTrue()
        ->and(DB::table('reviews')->exists())->toBeTrue()
        ->and(DB::table('bank_accounts')->exists())->toBeTrue()
        ->and(DB::table('notifications')->exists())->toBeTrue()
        ->and(DB::table('users')->where('status', 'suspended')->exists())->toBeTrue()
        ->and(DB::table('users')->where('phone', 'not like', '+974%')->exists())->toBeFalse();

    expect(app(LedgerReconciler::class)->run()->isClean())->toBeTrue();
});

it('attaches one to eight photos to every listing and leaves no orphan media', function (): void {
    demoCommand(['--users' => 6, '--ads' => 20])->assertSuccessful();

    $photosPerAd = DB::table('ads')
        ->leftJoin('media', fn ($join) => $join->on('media.model_id', '=', 'ads.id')->where('media.collection_name', 'images'))
        ->groupBy('ads.id')
        ->selectRaw('COUNT(media.id) as photos')
        ->pluck('photos')
        ->map(static fn (mixed $count): int => (int) $count);

    expect($photosPerAd->min())->toBeGreaterThanOrEqual(1)
        ->and($photosPerAd->max())->toBeLessThanOrEqual(8);

    Media::query()->distinct()->pluck('model_type')->each(function (string $type): void {
        /** @var class-string<Model> $class */
        $class = Relation::getMorphedModel($type) ?? $type;
        $table = (new $class)->getTable();

        expect(Media::query()->where('model_type', $type)->whereNotIn('model_id', DB::table($table)->select('id'))->exists())
            ->toBeFalse("Media points at missing {$table} rows.");
    });

    foreach (Media::query()->get() as $media) {
        expect(Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()))->toBeTrue();
    }
});

it('refuses to run twice without --fresh', function (): void {
    demoCommand(['--users' => 4, '--ads' => 12])->assertSuccessful();
    $users = User::query()->count();

    demoCommand(['--users' => 4, '--ads' => 12])->assertFailed();

    expect(User::query()->count())->toBe($users);
});

it('refuses to run in production without --force', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    demoCommand(['--users' => 4, '--ads' => 12])->assertFailed();

    expect(User::query()->where('email', 'like', '%@' . DemoOptions::EMAIL_DOMAIN)->exists())->toBeFalse();
});

it('refuses to run in production when the typed confirmation does not match', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    demoCommand(['--users' => 4, '--ads' => 12, '--force' => true])
        ->expectsQuestion('This fills the production database [' . config('database.connections.sqlite.database') . '] with demo data. Type its name to continue', 'yes')
        ->assertFailed();

    expect(User::query()->where('email', 'like', '%@' . DemoOptions::EMAIL_DOMAIN)->exists())->toBeFalse();
});

it('refuses to be seeded directly in production, around the command', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DemoDataSeeder::class)->run(new DemoOptions(users: 4, ads: 12)))
        ->toThrow(RuntimeException::class, 'never seeded in production');

    expect(User::query()->where('email', 'like', '%@' . DemoOptions::EMAIL_DOMAIN)->exists())->toBeFalse();
});

it('rejects counts outside the supported range', function (array $options): void {
    demoCommand($options)->assertExitCode(2);
})->with([
    'too few users' => [['--users' => 1]],
    'not a number' => [['--ads' => 'many']],
]);
