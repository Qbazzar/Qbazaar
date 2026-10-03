<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\Contracts\Badgeable;
use App\Enums\LocationType;
use App\Enums\ModerationRuleType;
use App\Enums\OfferStatus;
use App\Enums\ReportStatus;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\UserStatus;
use Illuminate\Pagination\LengthAwarePaginator;

it('links an input to its label, hint and required marker', function (): void {
    $this->blade('<x-admin.input name="title" label="العنوان" hint="يظهر للبائع" required />')
        ->assertSee('<label for="title"', false)
        ->assertSee('id="title"', false)
        ->assertSee('required', false)
        ->assertSee('aria-hidden="true">*</span>', false)
        ->assertSee('id="title-hint"', false)
        ->assertSee('aria-describedby="title-hint"', false)
        ->assertDontSee('aria-invalid', false);
});

it('announces a field error through aria-invalid and aria-describedby', function (): void {
    $this->withViewErrors(['name.ar' => 'الاسم مطلوب']);

    $this->blade('<x-admin.input name="name[ar]" label="الاسم (عربي)" />')
        ->assertSee('id="name-ar"', false)
        ->assertSee('<label for="name-ar"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="name-ar-error"', false)
        ->assertSee('<p id="name-ar-error"', false)
        ->assertSee('الاسم مطلوب');
});

it('links select, textarea and checkbox labels', function (): void {
    $this->blade('<x-admin.select name="type" label="النوع"><option value="a">A</option></x-admin.select>')
        ->assertSee('<label for="type"', false)
        ->assertSee('<select', false)
        ->assertSee('id="type"', false);

    $this->blade('<x-admin.textarea name="body" label="المحتوى" value="نص" />')
        ->assertSee('<label for="body"', false)
        ->assertSee('id="body"', false)
        ->assertSee('>نص</textarea>', false);

    $this->blade('<x-admin.checkbox name="is_active" label="مفعّل" unchecked-value="0" :checked="true" />')
        ->assertSee('<input type="hidden" name="is_active" value="0">', false)
        ->assertSee('id="is_active"', false)
        ->assertSee('<label for="is_active"', false)
        ->assertSee('checked', false);
});

it('gives the filter bar labelled controls and a full-width mobile search', function (): void {
    $this->blade(
        '<x-admin.filter-bar reset-url="/admin/ads" search="x" :filters="$filters" />',
        ['filters' => [['name' => 'status', 'label' => 'الحالة', 'placeholder' => 'كل الحالات', 'value' => 'b', 'options' => ['a' => 'A', 'b' => 'B']]]],
    )
        ->assertSee('role="search"', false)
        ->assertSee('<label for="filter-q" class="sr-only">', false)
        ->assertSee('<label for="filter-status" class="sr-only">', false)
        ->assertSee('w-full sm:w-64', false)
        ->assertSee('<option value="b" selected>', false)
        ->assertSee('href="/admin/ads"', false);
});

it('renders a table that stacks into labelled cards below md', function (): void {
    $this->blade(<<<'BLADE'
        <x-admin.table selectable :columns="['الاسم', ['label' => 'البريد', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]">
            <x-admin.table.row>
                <x-admin.table.select value="1" label="تحديد: أحمد" />
                <x-admin.table.cell primary>أحمد</x-admin.table.cell>
                <x-admin.table.cell label="البريد" secondary>a@example.com</x-admin.table.cell>
                <x-admin.table.actions><x-admin.icon-button href="/x" icon="eye" label="عرض" /></x-admin.table.actions>
            </x-admin.table.row>
        </x-admin.table>
        BLADE)
        ->assertSee('max-md:hidden', false)
        ->assertSee('md:table-row', false)
        ->assertSee('aria-label="تحديد الكل"', false)
        ->assertSee('data-bulk-item', false)
        ->assertSee('aria-label="تحديد: أحمد"', false)
        ->assertSee('<span class="shrink-0 text-xs font-semibold text-ink-500 md:hidden">البريد</span>', false)
        ->assertSee('hidden lg:table-cell', false)
        ->assertSee('<span class="sr-only">إجراءات</span>', false)
        ->assertSee('aria-label="عرض"', false);
});

it('shows the empty state instead of rows when the table is empty', function (): void {
    $this->blade('<x-admin.table :columns="[\'الاسم\', \'البريد\']" :empty="true" empty-message="لا يوجد شيء." />')
        ->assertSee('colspan="2"', false)
        ->assertSee('لا يوجد شيء.');
});

it('renders enum badges with their label and tone', function (): void {
    $this->blade('<x-admin.badge :status="$status" />', ['status' => UserStatus::SUSPENDED])
        ->assertSee('موقوف')
        ->assertSee('bg-red-50 text-red-700', false);

    $this->blade('<x-admin.badge tone="brand">مدير</x-admin.badge>')
        ->assertSee('bg-coral-soft text-coral-700', false)
        ->assertSee('مدير');
});

it('renders buttons as links or buttons with their variant', function (): void {
    $this->blade('<x-admin.button href="/admin/ads/create" icon="plus">جديد</x-admin.button>')
        ->assertSee('<a href="/admin/ads/create"', false)
        ->assertSee('bg-coral-600', false);

    $this->blade('<x-admin.button variant="danger" type="button">حذف</x-admin.button>')
        ->assertSee('<button type="button"', false)
        ->assertSee('bg-red-600', false);
});

it('renders the accessible confirm dialog', function (): void {
    $this->blade('<x-admin.confirm-dialog />')
        ->assertSee('<dialog id="qb-confirm" aria-labelledby="qb-confirm-title" aria-describedby="qb-confirm-message"', false)
        ->assertSee('<form method="dialog">', false)
        ->assertSee('value="cancel"', false)
        ->assertSee('value="confirm"', false);
});

it('renders RTL-safe pagination with brand tokens and no dark mode leakage', function (): void {
    $paginator = new LengthAwarePaginator(range(1, 10), 30, 10, 2, ['path' => '/admin/ads']);

    $html = (string) $this->blade('<x-admin.pagination :paginator="$paginator" />', ['paginator' => $paginator]);

    expect($html)
        ->toContain('aria-label="التنقل بين الصفحات"')
        ->toContain('aria-current="page"')
        ->toContain('rel="prev"')
        ->toContain('rel="next"')
        ->toContain('bg-coral-600')
        ->not->toContain('dark:')
        ->not->toContain('rounded-l-')
        ->not->toContain('rounded-r-');
});

it('gives every badge enum an Arabic label and a known tone', function (Badgeable $case): void {
    expect($case->label()['ar'])->not->toBe('')
        ->and($case->label()['en'])->not->toBe('')
        ->and($case->tone())->toBeIn(['neutral', 'success', 'warning', 'danger', 'info', 'brand', 'violet']);
})->with(fn (): array => array_merge(
    AdStatus::cases(),
    UserStatus::cases(),
    OfferStatus::cases(),
    ReportStatus::cases(),
    SupportTicketStatus::cases(),
    SupportTicketPriority::cases(),
    LocationType::cases(),
    ModerationRuleType::cases(),
));
