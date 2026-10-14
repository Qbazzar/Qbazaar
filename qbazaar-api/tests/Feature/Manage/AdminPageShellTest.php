<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Admin\TimelineEntry;
use App\Support\Admin\DetachedForm;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    app()->setLocale('ar');
});

describe('x-admin.page', function (): void {
    it('renders the only h1, the breadcrumb and the full width without a form', function (): void {
        $view = $this->blade(<<<'BLADE'
            <x-admin.page title="تعديل الإعلان" :crumbs="[['الإعلانات', '/admin/ads'], ['إعلان #4821', '/admin/ads/1'], 'تعديل']">
                <p>المحتوى</p>
            </x-admin.page>
            BLADE);

        expect(substr_count((string) $view, '<h1'))->toBe(1);
        $view->assertSee('data-qb-page', false)
            ->assertSee('mx-auto w-full max-w-[96rem]', false)
            ->assertSee('aria-label="مسار التنقل"', false)
            ->assertSee('<a href="/admin/ads"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('<bdi dir="ltr">#4821</bdi>', false)
            ->assertSee('data-qb-page-header', false)
            ->assertSee('data-qb-header-sentinel', false)
            ->assertDontSee('<form', false)
            ->assertDontSee('<aside', false)
            ->assertDontSee('xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]', false);
    });

    it('applies the width presets', function (string $width, string $class): void {
        $this->blade('<x-admin.page title="عنوان" :width="$width">x</x-admin.page>', ['width' => $width])
            ->assertSee("mx-auto w-full {$class}", false);
    })->with([
        'full' => ['full', 'max-w-[96rem]'],
        'form' => ['form', 'max-w-6xl'],
        'reading' => ['reading', 'max-w-3xl'],
    ]);

    it('wraps header and grid in the form so the sticky Save and side fields post together', function (): void {
        $view = $this->blade(<<<'BLADE'
            <x-admin.page title="تعديل" :form="['/admin/ads/1', 'PUT']" cancel="/admin/ads/1" submit="حفظ التعديلات">
                <x-admin.input name="title" label="العنوان" />
                <x-slot:side><x-admin.checkbox name="is_featured" label="مميّز" /></x-slot:side>
            </x-admin.page>
            BLADE);

        $html = (string) $view;
        $formStart = strpos($html, '<form data-qb-page id="page-form" method="POST" action="/admin/ads/1"');

        expect($formStart)->not->toBeFalse()
            ->and(strpos($html, '</header>'))->toBeGreaterThan($formStart)
            ->and(strpos($html, 'name="is_featured"'))->toBeLessThan(strrpos($html, '</form>'))
            ->and(substr_count($html, 'حفظ التعديلات'))->toBe(2);
        $view->assertSee('name="_token"', false)
            ->assertSee('<input type="hidden" name="_method" value="PUT">', false)
            ->assertSee('href="/admin/ads/1"', false)
            ->assertSee('max-sm:hidden', false)
            ->assertSee('mt-6 grid gap-2 sm:hidden', false);
    });

    it('moves Save to the bottom pair on phones so it renders once per screen size', function (): void {
        $html = (string) $this->blade('<x-admin.page title="تعديل" :form="[\'/x\', \'PUT\']" cancel="/back" submit="حفظ">x</x-admin.page>');
        $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
        $bottom = substr($html, strpos($html, 'mt-6 grid gap-2 sm:hidden'));

        // Header Save hides on phones; the bottom pair (phones only) carries the visible one.
        expect(preg_match('/<button[^>]*max-sm:hidden[^>]*>(?:(?!<\/button>).)*حفظ/s', $header))->toBe(1)
            ->and($bottom)->toContain('حفظ')
            ->and(substr_count($html, 'حفظ'))->toBe(2);
    });

    it('keeps the sticky bar as wide as the content column whatever the screen width', function (): void {
        $this->blade('<x-admin.page title="عنوان">x</x-admin.page>')
            ->assertSee('before:-inset-x-[100vw]', false)
            ->assertDontSee('-mx-4', false);
    });

    it('leaves the buttons to the page when submit is false', function (): void {
        $this->blade('<x-admin.page title="عنوان" :form="[\'action\' => \'/x\']" :submit="false">x</x-admin.page>')
            ->assertSee('method="POST" action="/x"', false)
            ->assertDontSee('name="_method"', false)
            ->assertDontSee('sm:hidden', false);
    });

    it('adds the sticky side column only when the side slot has content', function (): void {
        $this->blade('<x-admin.page title="عنوان">x<x-slot:side><p>جانبي</p></x-slot:side></x-admin.page>')
            ->assertSee('xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]', false)
            ->assertSee('<aside aria-label="معلومات جانبية"', false)
            ->assertSee('data-qb-side', false)
            ->assertSee('xl:data-sticky:sticky', false);

        $this->blade('<x-admin.page title="عنوان" :sticky-side="false" side-label="البائع">x<x-slot:side><p>جانبي</p></x-slot:side></x-admin.page>')
            ->assertSee('<aside aria-label="البائع"', false)
            ->assertDontSee('data-qb-side', false);
    });

    it('opens the form with a validation summary that links to each field', function (): void {
        $this->withViewErrors(['title' => 'العنوان مطلوب.', 'name.ar' => 'الاسم مطلوب.']);

        $this->blade('<x-admin.page title="تعديل" :form="[\'/x\', \'PUT\']"><x-admin.input name="title" label="العنوان" /></x-admin.page>')
            ->assertSee('role="alert"', false)
            ->assertSee('data-qb-autofocus', false)
            ->assertSee('تعذّر الحفظ. راجع الحقول التالية:')
            ->assertSee('<a href="#title" data-qb-field-link', false)
            ->assertSee('<a href="#name-ar" data-qb-field-link', false)
            ->assertSee('aria-invalid="true"', false);
    });

    it('shows no validation summary on pages without a form', function (): void {
        $this->withViewErrors(['title' => 'العنوان مطلوب.']);

        $this->blade('<x-admin.page title="عرض">x</x-admin.page>')
            ->assertDontSee('data-qb-field-link', false);
    });

    it('renders badges, actions, the overflow menu and the subtitle in the header', function (): void {
        $this->blade(<<<'BLADE'
            <x-admin.page title="إعلان" subtitle="نُشر في 2026-09-28">
                <x-slot:badges><x-admin.badge tone="success">نشط</x-admin.badge></x-slot:badges>
                <x-slot:actions><x-admin.button href="/edit" variant="secondary">تعديل</x-admin.button></x-slot:actions>
                <x-slot:menu><x-admin.menu.item href="/open" icon="eye">فتح في الموقع</x-admin.menu.item></x-slot:menu>
                x
            </x-admin.page>
            BLADE)
            ->assertSeeInOrder(['<h1', 'نشط', 'href="/edit"', '<details data-qb-menu', 'فتح في الموقع', 'نُشر في 2026-09-28'], false)
            ->assertSee('<span class="sr-only">المزيد من الإجراءات</span>', false);
    });
});

describe('x-admin.breadcrumb', function (): void {
    it('draws its separators as a chevron that flips in RTL instead of a mirrored character', function (): void {
        $view = $this->blade('<x-admin.breadcrumb :items="[[\'أ\', \'/a\'], [\'ب\', \'/b\'], \'ج\']" />');

        expect(substr_count((string) $view, 'data-qb-crumb-separator'))->toBe(2);
        $view->assertSee('rtl:rotate-180', false)->assertDontSee('‹', false)->assertDontSee('›', false);
    });
});

describe('layout integration', function (): void {
    beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        actingAs(User::factory()->create()->assignRole('super_admin'));
    });

    it('turns the top-bar heading into a section label when the page owns the h1', function (): void {
        $view = $this->blade(<<<'BLADE'
            @extends('admin.layout')
            @section('heading', 'الإعلانات')
            @section('content')
                <x-admin.page title="تعديل الإعلان" :form="['/admin/ads/1', 'PUT']">
                    <x-slot:menu>
                        <x-admin.menu.item action="/admin/ads/1/suspend" icon="ban" danger confirm="إيقاف الإعلان؟">إيقاف</x-admin.menu.item>
                    </x-slot:menu>
                    <x-admin.input name="title" label="العنوان" />
                </x-admin.page>
            @endsection
            BLADE);

        $html = (string) $view;
        $formId = DetachedForm::id('/admin/ads/1/suspend');

        expect(substr_count($html, '<h1'))->toBe(1)
            // The action form is printed after </main>, never nested in the page form.
            ->and(strpos($html, "<form id=\"{$formId}\""))->toBeGreaterThan(strpos($html, '</main>'));
        $view->assertSee('<p class="truncate text-sm font-bold text-ink-500">الإعلانات</p>', false)
            ->assertSee("form=\"{$formId}\"", false)
            ->assertSee('data-confirm="إيقاف الإعلان؟"', false)
            ->assertSee('IntersectionObserver', false)
            ->assertSee('details[data-qb-menu][open]', false)
            ->assertSee("form.dataset.submitting = '1'", false);
    });

    it('lifts the submit guard on its own and skips forms that download a file', function (): void {
        $this->blade(<<<'BLADE'
            @extends('admin.layout')
            @section('content')<p>x</p>@endsection
            BLADE)
            ->assertSee("hasAttribute('data-download')", false)
            ->assertSee('RELEASE_AFTER_MS', false)
            ->assertSee('overflow-x-clip', false);
    });

    it('keeps the top-bar h1 on pages that do not use the shell', function (): void {
        $view = $this->blade(<<<'BLADE'
            @extends('admin.layout')
            @section('heading', 'سجل النشاط')
            @section('content')<p>قائمة</p>@endsection
            BLADE);

        expect(substr_count((string) $view, '<h1'))->toBe(1);
        $view->assertSee('<h1 class="truncate text-base font-bold sm:text-lg">سجل النشاط</h1>', false)
            ->assertDontSee('IntersectionObserver', false);
    });
});

describe('x-admin.form-section', function (): void {
    it('lays fields out by the card width and keeps the field wiring', function (): void {
        $this->withViewErrors(['price' => 'السعر غير صالح.']);

        $view = $this->blade(<<<'BLADE'
            <x-admin.form-section title="السعر والحالة" description="يظهر للمشترين." :cols="3" id="price-section">
                <x-admin.input name="price" label="السعر" hint="بالريال" required />
                <x-admin.textarea name="note" label="ملاحظة" class="col-span-full" />
            </x-admin.form-section>
            BLADE);

        $view->assertSee('<section id="price-section" aria-labelledby="price-section-title"', false)
            ->assertSee('@container', false)
            ->assertSee('@min-[34rem]:grid-cols-2 @min-[46rem]:grid-cols-3', false)
            ->assertSee('<h2 id="price-section-title"', false)
            ->assertSee('يظهر للمشترين.')
            ->assertSee('aria-describedby="price-hint price-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-hidden="true">*</span>', false)
            ->assertSee('class="col-span-full"', false);

        $this->blade('<x-admin.form-section title="المحتوى" :cols="2">x</x-admin.form-section>')
            ->assertSee('@min-[34rem]:grid-cols-2', false)
            ->assertDontSee('@min-[46rem]', false);

        $this->blade('<x-admin.form-section title="المحتوى">x</x-admin.form-section>')
            ->assertSee('grid gap-5', false)
            ->assertDontSee('@min-[34rem]', false);
    });
});

describe('x-admin.menu', function (): void {
    it('renders links, danger items and confirmed actions on detached forms', function (): void {
        $view = $this->blade(<<<'BLADE'
            <x-admin.menu>
                <x-admin.menu.item href="/admin/ads/1" icon="eye">عرض</x-admin.menu.item>
                <x-admin.menu.divider />
                <x-admin.menu.item action="/admin/ads/1" method="DELETE" icon="trash" danger confirm="حذف نهائي؟">حذف</x-admin.menu.item>
                <x-admin.menu.item action="/admin/ads/1" method="DELETE" danger>حذف مرة أخرى</x-admin.menu.item>
            </x-admin.menu>
            @stack('admin-detached-forms')
            BLADE);

        $html = (string) $view;
        $formId = DetachedForm::id('/admin/ads/1', 'DELETE');

        expect(substr_count($html, "<form id=\"{$formId}\""))->toBe(1)
            ->and(substr_count($html, "form=\"{$formId}\""))->toBe(2);
        $view->assertSee('<a href="/admin/ads/1" data-qb-menu-item', false)
            ->assertSee('text-red-700 hover:bg-red-50', false)
            ->assertSee('<hr class="my-1 border-ink-200">', false)
            ->assertSee('data-confirm="حذف نهائي؟"', false)
            ->assertSee('<input type="hidden" name="_method" value="DELETE">', false)
            ->assertDontSee('role="menu"', false);
    });

    it('places a form button inside a page form without nesting forms', function (): void {
        $html = (string) $this->blade(<<<'BLADE'
            <x-admin.page title="تعديل" :form="['/admin/ads/1', 'PUT']">
                <x-admin.form-button action="/admin/ads/1/approve" variant="success" icon="check" :fields="['note' => 'ok']" confirm-tone="primary" confirm="اعتماد؟">اعتماد</x-admin.form-button>
            </x-admin.page>
            @stack('admin-detached-forms')
            BLADE);

        $formId = DetachedForm::id('/admin/ads/1/approve', 'POST', ['note' => 'ok']);
        $pageFormEnd = strpos($html, '</form>');

        expect(strpos($html, "<form id=\"{$formId}\""))->toBeGreaterThan($pageFormEnd)
            ->and($html)->toContain("form=\"{$formId}\"")
            ->and($html)->toContain('data-confirm-tone="primary"')
            ->and($html)->toContain('<input type="hidden" name="note" value="ok">')
            ->and($html)->toContain('bg-emerald-700');
    });
});

describe('side column pieces', function (): void {
    it('renders a labelled side card with a link and a meta list', function (): void {
        $this->blade(<<<'BLADE'
            <x-admin.side-card title="بيانات الإعلان" id="ad-meta" :link="['السجل الكامل', '/admin/activity']">
                <x-admin.meta-list>
                    <x-admin.meta-item label="المعرّف" ltr>#4821</x-admin.meta-item>
                    <x-admin.meta-item label="الحالة">نشط</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>
            BLADE)
            ->assertSee('<section aria-labelledby="ad-meta-title"', false)
            ->assertSee('<h2 id="ad-meta-title"', false)
            ->assertSee('href="/admin/activity"', false)
            ->assertSee('<dt class="shrink-0 text-ink-500">المعرّف</dt>', false)
            ->assertSee('<span dir="ltr">#4821</span>', false);
    });

    it('renders timeline entries and an empty state', function (): void {
        $entries = collect([
            new TimelineEntry('اعتماد الإعلان', 'سارة العلي', CarbonImmutable::parse('2026-09-28 10:00'), 'success'),
        ]);

        $this->blade('<x-admin.timeline :entries="$entries" :link="[\'السجل الكامل\', \'/admin/activity\']" />', ['entries' => $entries])
            ->assertSee('<ol class="grid">', false)
            ->assertSee('اعتماد الإعلان')
            ->assertSee('سارة العلي')
            ->assertSee('datetime="2026-09-28T10:00:00', false)
            ->assertSee('border-emerald-600', false)
            ->assertSee('href="/admin/activity"', false);

        $this->blade('<x-admin.timeline :entries="collect()" />')
            ->assertSee('لا يوجد نشاط مسجّل بعد.')
            ->assertSee('py-8', false)
            ->assertDontSee('<ol', false);
    });

    it('shows a cover-first gallery and an empty state', function (): void {
        $this->blade('<x-admin.gallery :images="$images" />', ['images' => [
            ['thumb' => '/t/1.jpg', 'full' => '/f/1.jpg', 'alt' => 'صورة 1'],
            ['/t/2.jpg', '/f/2.jpg'],
        ]])
            ->assertSee('col-span-2 row-span-2', false)
            ->assertSee('الغلاف')
            ->assertSee('href="/f/1.jpg" target="_blank" rel="noopener"', false)
            ->assertSee('alt="الصورة 2 (تُفتح في تبويب جديد)"', false);

        $this->blade('<x-admin.gallery :images="[]" />')
            ->assertSee('لا توجد صور.')
            ->assertDontSee('<ul', false);
    });

    it('shows a failing widget and a loading placeholder', function (): void {
        $this->blade('<x-admin.error-state retry-url="/admin/ads/1" />')
            ->assertSee('تعذّر تحميل هذا القسم.')
            ->assertSee('href="/admin/ads/1"', false)
            ->assertSee('إعادة المحاولة');

        $view = $this->blade('<x-admin.skeleton :lines="4" avatar />');
        expect(substr_count((string) $view, 'motion-safe:animate-pulse'))->toBe(6);
        $view->assertSee('role="status"', false)
            ->assertSee('<span class="sr-only">جارٍ التحميل…</span>', false);
    });
});

describe('feedback', function (): void {
    it('announces danger alerts and keeps info alerts polite', function (): void {
        $this->blade('<x-admin.alert tone="danger" title="خطأ">تفاصيل</x-admin.alert>')
            ->assertSee('role="alert"', false)
            ->assertSee('bg-red-50 text-red-700', false)
            ->assertDontSee('data-qb-autofocus', false);

        $this->blade('<x-admin.alert>معلومة</x-admin.alert>')
            ->assertSee('role="status"', false)
            ->assertSee('bg-sky-50 text-sky-700', false);
    });

    it('offers a compact empty state for cards', function (): void {
        $this->blade('<x-admin.empty-state message="لا شيء" compact />')->assertSee('py-8', false)->assertDontSee('py-16', false);
        $this->blade('<x-admin.empty-state message="لا شيء" />')->assertSee('py-16', false);
    });
});

it('isolates latin runs in breadcrumb labels without breaking escaping', function (): void {
    $html = (string) $this->blade('<x-admin.breadcrumb :items="[[\'<b>R&D</b> 5\', \'/x\'], \'iPhone 14 Pro\']" />');

    expect($html)->toContain('&lt;<bdi dir="ltr">b</bdi>&gt;<bdi dir="ltr">R</bdi>&amp;<bdi dir="ltr">D</bdi>&lt;/<bdi dir="ltr">b</bdi>&gt; <bdi dir="ltr">5</bdi>')
        ->and($html)->toContain('<bdi dir="ltr">iPhone 14 Pro</bdi>')
        ->and(Str::contains($html, '<b>'))->toBeFalse();
});
