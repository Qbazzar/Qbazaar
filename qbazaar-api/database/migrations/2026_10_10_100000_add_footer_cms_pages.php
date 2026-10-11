<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The footer links to pages that did not exist yet. They go in as published
 * placeholders the owner edits in /admin/pages. Insert-only: a slug that is
 * already there (edited or created by hand) is never touched.
 *
 * Footer columns (display_order follows the footer order):
 *   Classifieds : careers, press, magazine, connect, mobile-apps
 *   Information : safety-tips, report-vulnerability, youth-protection,
 *                 accessibility, privacy-settings, legal-notice
 *   Businesses  : real-estate-classifieds, pro-info, pro-packages, advertise
 *   General     : popular-searches, overview, vehicle-appraisal
 */
return new class extends Migration
{
    private const BODY = [
        'ar' => '<p>سيتم إضافة محتوى هذه الصفحة قريباً.</p>',
        'en' => '<p>Content for this page is coming soon.</p>',
    ];

    /** @var array<string, array{ar: string, en: string}> slug => title, in footer order */
    private const PAGES = [
        'careers' => ['ar' => 'الوظائف', 'en' => 'Careers'],
        'press' => ['ar' => 'الصحافة', 'en' => 'Press'],
        'magazine' => ['ar' => 'مجلة الإعلانات المبوبة', 'en' => 'Classifieds Magazine'],
        'connect' => ['ar' => 'تواصل', 'en' => 'Connect'],
        'mobile-apps' => ['ar' => 'تطبيقات الجوال', 'en' => 'Mobile Apps'],
        'safety-tips' => ['ar' => 'نصائح السلامة', 'en' => 'Safety Tips'],
        'report-vulnerability' => ['ar' => 'الإبلاغ عن ثغرة أمنية', 'en' => 'Report a Vulnerability'],
        'youth-protection' => ['ar' => 'حماية الشباب', 'en' => 'Youth Protection'],
        'accessibility' => ['ar' => 'بيان إمكانية الوصول', 'en' => 'Accessibility Statement'],
        'privacy-settings' => ['ar' => 'إعدادات الخصوصية', 'en' => 'Privacy Settings'],
        'legal-notice' => ['ar' => 'إشعار قانوني', 'en' => 'Legal Notice'],
        'real-estate-classifieds' => ['ar' => 'إعلانات العقارات', 'en' => 'Real Estate Classifieds'],
        'pro-info' => ['ar' => 'مركز معلومات PRO', 'en' => 'PRO Info Point'],
        'pro-packages' => ['ar' => 'باقات PRO', 'en' => 'PRO Packages'],
        'advertise' => ['ar' => 'أعلن معنا', 'en' => 'Advertise with Us'],
        'popular-searches' => ['ar' => 'عمليات البحث الشائعة', 'en' => 'Popular Searches'],
        'overview' => ['ar' => 'عرض النظرة العامة', 'en' => 'View Overview'],
        'vehicle-appraisal' => ['ar' => 'تقييم المركبات', 'en' => 'Vehicle Appraisal'],
    ];

    /** Clear of the seeded about/terms/privacy/contact pages (1-4). */
    private const FIRST_DISPLAY_ORDER = 100;

    public function up(): void
    {
        $existing = DB::table('pages')->whereIn('slug', array_keys(self::PAGES))->pluck('slug')->all();
        $order = self::FIRST_DISPLAY_ORDER;
        $now = now();
        $rows = [];

        foreach (self::PAGES as $slug => $title) {
            if (! in_array($slug, $existing, true)) {
                $rows[] = $this->row($slug, $title, $order, $now);
            }
            $order++;
        }

        if ($rows !== []) {
            DB::table('pages')->insert($rows);
            Cache::forget('pages.list');
        }
    }

    public function down(): void
    {
        // Only rows still holding the untouched placeholder go; edited pages stay.
        DB::table('pages')->whereIn('slug', array_keys(self::PAGES))->get(['id', 'body'])
            ->filter(fn (object $page): bool => json_decode((string) $page->body, true) === self::BODY)
            ->each(fn (object $page): int => DB::table('pages')->where('id', $page->id)->delete());
        Cache::forget('pages.list');
    }

    /**
     * @param array{ar: string, en: string} $title
     * @return array<string, mixed>
     */
    private function row(string $slug, array $title, int $order, mixed $now): array
    {
        return [
            'id' => (string) Str::ulid(),
            'slug' => $slug,
            'title' => json_encode($title, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'body' => json_encode(self::BODY, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'meta_description' => null,
            'is_published' => true,
            'published_at' => $now,
            'display_order' => $order,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
};
