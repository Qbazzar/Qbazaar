<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Admin\EntityTimeline;
use App\Services\Admin\TimelineEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['scout.driver' => null]);
    app()->setLocale('ar');

    $this->subject = User::factory()->create(['full_name' => 'سارة العلي']);
    $this->admin = User::factory()->create(['full_name' => 'أحمد المشرف']);
    // Observers log the sign-ups above; each test writes the rows it reads.
    Activity::query()->delete();
});

function logTimelineRow(User $subject, string $event, ?User $causer = null, string $description = 'Ad updated'): void
{
    $entry = activity('admin')->performedOn($subject)->event($event);

    if ($causer !== null) {
        $entry->causedBy($causer);
    }

    $entry->log($description);
}

it('returns the newest entries first and honours the limit', function (): void {
    foreach (['signed_up', 'email_changed', 'status_changed'] as $event) {
        logTimelineRow($this->subject, $event, $this->admin);
    }

    $entries = app(EntityTimeline::class)->forSubject($this->subject, limit: 2);

    expect($entries)->toHaveCount(2)
        ->and($entries->first())->toBeInstanceOf(TimelineEntry::class)
        ->and($entries->pluck('title')->all())->toBe(['تغيير الحالة', 'تغيير البريد الإلكتروني']);
});

it('only reads the rows of the given record', function (): void {
    $other = User::factory()->create();
    logTimelineRow($other, 'status_changed', $this->admin);
    logTimelineRow($this->subject, 'signed_up');

    expect(app(EntityTimeline::class)->forSubject($this->subject)->pluck('title')->all())->toBe(['إنشاء الحساب']);
});

it('names admin actions, the acting staff member and the system', function (): void {
    logTimelineRow($this->subject, 'admin.users.suspend', $this->admin, 'admin.users.suspend');
    logTimelineRow($this->subject, 'admin.users.something_new', $this->admin, 'admin.users.something_new');
    logTimelineRow($this->subject, 'withdrawal_paid', null, 'Withdrawal paid');

    $entries = app(EntityTimeline::class)->forSubject($this->subject);

    expect($entries[2]->title)->toBe('إيقاف الحساب')
        ->and($entries[2]->actor)->toBe('أحمد المشرف')
        ->and($entries[2]->tone)->toBe('danger')
        // An untranslated admin route name never reaches the reader.
        ->and($entries[1]->title)->toBe('تحديث')
        ->and($entries[1]->tone)->toBe('neutral')
        ->and($entries[0]->title)->toBe('Withdrawal paid')
        ->and($entries[0]->actor)->toBe('النظام');
});

it('filters to the requested events for a status history', function (): void {
    logTimelineRow($this->subject, 'status_changed', $this->admin);
    logTimelineRow($this->subject, 'email_changed', $this->admin);

    $entries = app(EntityTimeline::class)->forSubject($this->subject, events: ['status_changed']);

    expect($entries->pluck('title')->all())->toBe(['تغيير الحالة']);
});

it('costs the same two queries for 1 or 30 log rows', function (int $rows): void {
    $causers = User::factory()->count(3)->create();
    Activity::query()->delete();

    for ($i = 0; $i < $rows; $i++) {
        logTimelineRow($this->subject, 'status_changed', $i % 4 === 3 ? null : $causers[$i % 3]);
    }

    DB::enableQueryLog();
    $entries = app(EntityTimeline::class)->forSubject($this->subject, limit: 30);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($entries)->toHaveCount($rows)
        ->and($queries)->toBe(2);
})->with([1, 30]);
