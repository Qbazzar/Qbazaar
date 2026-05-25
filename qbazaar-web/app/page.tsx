import Link from 'next/link';
import { ArrowLeft, CheckCircle2, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { HomeCategoryStrip } from '@/components/home/HomeCategoryStrip';
import { HomeFeaturedAds } from '@/components/home/HomeFeaturedAds';
import { HomeLatestAds } from '@/components/home/HomeLatestAds';
import { RecentlyViewedStrip } from '@/components/account/RecentlyViewedStrip';
import { t } from '@/lib/i18n/messages';

/**
 * Homepage — translated from `DOCS/bazzar/src/pages/home.jsx` (Variation A
 * hero). The hero uses the warm coral palette + Instrument-Serif italic
 * headline; categories + latest-ads sections are rendered as client islands
 * so they can lean on TanStack Query.
 */
export default function HomePage() {
  return (
    <main className="bg-cream-50 min-h-svh">
      {/* HERO */}
      <section className="border-ink-200 border-b bg-cream-100">
        <div className="mx-auto grid w-full max-w-6xl items-center gap-10 px-6 py-16 md:grid-cols-[1.2fr_1fr] md:gap-14 md:py-24">
          <div>
            <p className="text-coral text-xs font-bold uppercase tracking-[0.18em]">
              {t('home.hero.eyebrow', 'سوق قطر')}
            </p>
            <h1 className="font-display mt-4 text-5xl leading-[1.05] tracking-tight text-balance text-ink-900 md:text-[4.5rem]">
              {t('home.hero.headline', "سوق قطر الودود للإعلانات المبوبة")}
            </h1>
            <p className="text-ink-700 mt-5 max-w-md text-base leading-relaxed md:text-lg">
              {t(
                'home.hero.subtitle',
                'بيع واشترِ واكتشف من جيرانك — من سيارة مستعملة في الخليج الغربي إلى قطة صغيرة في الخور. مجاناً، آمناً، وبلمسة قطرية.',
              )}
            </p>

            <div className="mt-7 flex flex-wrap items-center gap-3">
              <Button
                asChild
                size="lg"
                className="bg-coral hover:bg-coral/90 rounded-full px-6 text-white"
              >
                <Link href="/post-ad">
                  {t('home.hero.cta_post', 'انشر إعلانك')}
                </Link>
              </Button>
              <Button
                asChild
                size="lg"
                variant="outline"
                className="rounded-full px-6"
              >
                <Link href="/ads">
                  {t('home.hero.cta_browse', 'تصفّح الإعلانات')}
                </Link>
              </Button>
            </div>

            <div className="text-ink-700 mt-10 flex flex-wrap gap-x-7 gap-y-2 text-sm">
              <span className="inline-flex items-center gap-2">
                <ShieldCheck className="text-sage size-4" />
                {t('home.trust.verified', 'بائعون موثّقون')}
              </span>
              <span className="inline-flex items-center gap-2">
                <CheckCircle2 className="text-sage size-4" />
                {t('home.trust.free_to_post', 'النشر مجاني')}
              </span>
            </div>
          </div>

          <div className="relative hidden h-[420px] items-center justify-center md:flex">
            <div className="bg-coral/15 absolute inset-0 rounded-[40px] blur-3xl" />
            <div className="bg-cream-200 relative grid h-full w-full place-items-center overflow-hidden rounded-3xl">
              <svg
                width="280"
                height="280"
                viewBox="0 0 280 280"
                className="text-terracotta drop-shadow-sm"
                fill="currentColor"
              >
                <circle cx="140" cy="140" r="120" opacity="0.12" />
                <circle cx="140" cy="140" r="74" opacity="0.18" />
                <circle cx="140" cy="140" r="40" />
              </svg>
            </div>
          </div>
        </div>
      </section>

      {/* TOP CATEGORIES */}
      <section className="mx-auto w-full max-w-6xl px-6 py-16">
        <SectionHeader
          kicker={t('home.sections.top_categories', 'أهم الأقسام')}
          title={t('home.sections.top_categories_title', 'كل الأسواق في قطر')}
          actionLabel={t('home.sections.view_more', 'كل الأقسام')}
          actionHref="/categories"
        />
        <HomeCategoryStrip />
      </section>

      {/* FEATURED — editorial cohort, hidden when empty */}
      <HomeFeaturedAds />

      {/* RECENTLY VIEWED — auth-only, hidden when empty */}
      <section className="mx-auto w-full max-w-6xl px-6 pb-4">
        <RecentlyViewedStrip />
      </section>

      {/* LATEST ADS */}
      <section className="mx-auto w-full max-w-6xl px-6 pb-20">
        <SectionHeader
          kicker={t('home.sections.latest_ads', 'أحدث الإعلانات')}
          title={t('home.sections.latest_ads_title', 'طازج من الحيّ')}
          actionLabel={t('home.sections.view_more', 'عرض الكل')}
          actionHref="/ads"
        />
        <HomeLatestAds />
      </section>
    </main>
  );
}

function SectionHeader({
  kicker,
  title,
  actionLabel,
  actionHref,
}: {
  kicker: string;
  title: string;
  actionLabel?: string;
  actionHref?: string;
}) {
  return (
    <div className="mb-6 flex items-end justify-between gap-3">
      <div>
        <p className="text-ink-500 text-xs font-bold uppercase tracking-[0.18em]">
          {kicker}
        </p>
        <h2 className="font-display mt-1 text-3xl text-ink-900 md:text-4xl">
          {title}
        </h2>
      </div>
      {actionLabel && actionHref ? (
        <Link
          href={actionHref}
          className="text-coral inline-flex items-center gap-1 text-sm font-medium hover:underline"
        >
          {actionLabel}
          <ArrowLeft className="size-3.5 rtl:rotate-180" />
        </Link>
      ) : null}
    </div>
  );
}
