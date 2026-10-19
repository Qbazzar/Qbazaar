<?php

declare(strict_types=1);

use App\Models\StaffBroadcast;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('keeps admin controllers free of hardcoded flash and status text', function (): void {
    $offences = [];
    $files = Finder::create()->files()->in(app_path('Http/Controllers/Manage'))->name('*.php');

    foreach ($files as $file) {
        foreach (preg_split('/\R/', $file->getContents()) ?: [] as $number => $line) {
            $code = trim($line);
            if ($code === '' || str_starts_with($code, '*') || str_starts_with($code, '//') || str_starts_with($code, '/*')) {
                continue;
            }

            $literalFlash = preg_match('/->with(Errors)?\(\s*[\'"](status|error|success|warning)[\'"]\s*,\s*[\'"]/', $code) === 1;
            $arabic = preg_match('/\p{Arabic}/u', $code) === 1;

            if ($literalFlash || $arabic) {
                $offences[] = $file->getRelativePathname() . ':' . ($number + 1) . ' ' . $code;
            }
        }
    }

    expect($offences)->toBe([]);
});

it('leaves no mention of the retired Filament panel in the code', function (): void {
    $mentions = [];
    $files = Finder::create()->files()->name('*.php')
        ->in([app_path(), config_path(), base_path('routes'), lang_path(), database_path(), resource_path('views')]);

    foreach ($files as $file) {
        if (stripos($file->getContents(), 'filament') !== false) {
            $mentions[] = $file->getRelativePathname();
        }
    }

    expect($mentions)->toBe([]);
});

it('flashes admin messages in the admin language', function (string $locale, string $expected): void {
    $owner = User::factory()->create();
    $ticket = SupportTicket::query()->create(['user_id' => $owner->id, 'subject' => 'S', 'category' => 'general', 'body' => 'b']);
    actingAs(User::factory()->create()->assignRole('support'));
    app()->setLocale($locale);

    $this->post(route('admin.support.assign', $ticket))->assertSessionHas('status', $expected);
})->with([
    'arabic' => ['ar', 'تم إسناد التذكرة إليك'],
    'english' => ['en', 'The ticket was assigned to you.'],
]);

it('blocks every write, not just page views, for an enforced role until two-factor is on', function (): void {
    config(['qbazaar.admin.two_factor.enforce' => true]);
    actingAs(User::factory()->create()->assignRole('super_admin'));

    $this->post(route('admin.broadcasts.store'), ['segment' => 'all', 'title_ar' => 'عنوان', 'body_ar' => 'نص'])
        ->assertRedirect(route('admin.profile.two-factor.show'));
    $this->put(route('admin.roles.permissions.update'), ['permissions' => []])
        ->assertRedirect(route('admin.profile.two-factor.show'));

    expect(StaffBroadcast::query()->count())->toBe(0)
        ->and(Role::findByName('support', 'web')->permissions()->count())->toBeGreaterThan(0);
});
