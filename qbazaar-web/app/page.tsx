import { SectionHeader } from '@/components/design-system/SectionHeader';
import { HomeCategoryStrip } from '@/components/home/HomeCategoryStrip';
import { HomeCityTags } from '@/components/home/HomeCityTags';
import { HomeFeaturedCompanies } from '@/components/home/HomeFeaturedCompanies';
import { HomeFeedAds } from '@/components/home/HomeFeedAds';
import { HomeRecentlyViewed } from '@/components/home/HomeRecentlyViewed';
import { HomeSearchBar } from '@/components/home/HomeSearchBar';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

/**
 * Home page on the new design (728:44663 / 561:22024 / 584:25249). The page
 * is a server component; each data-driven section is a client island reading
 * the one cached home feed query.
 */
export default async function HomePage() {
  // Pages render in parallel with the root layout, so prime the request
  // locale here too before the server-side t() calls below.
  await resolveServerLocale();
  return (
    <main className="bg-qb-page font-qb text-qb-ink">
      <HomeHero />
      <section aria-labelledby="home-categories" className="mx-auto max-w-[1440px] px-qb-gutter py-5">
        <SectionHeader
          id="home-categories"
          title={t('home.sections.categories_title', 'تصفّح الأقسام')}
          subtitle={t('home.sections.categories_sub', 'أهم ما يُعرض في منطقتك')}
          action={{ href: '/categories', label: t('home.sections.view_all', 'عرض الكل') }}
          className="mb-[22px]"
        />
        <HomeCategoryStrip />
      </section>
      <HomeFeedAds
        list="recommended"
        id="home-recommended"
        title={t('home.sections.recommended_title', 'مختارة لك')}
        subtitle={t('home.sections.recommended_sub', 'عروض منتقاة قريبة منك')}
        eager
      />
      <HomeRecentlyViewed />
      <HomeFeaturedCompanies />
      <HomeFeedAds
        list="best_selling"
        id="home-best-selling"
        title={t('home.sections.best_selling_title', 'الأكثر مبيعاً')}
        subtitle={t('home.sections.best_selling_sub', 'منتجات رائجة يحبها الناس.')}
        className="pb-10"
      />
      <FindPlaces />
    </main>
  );
}

function HomeHero() {
  return (
    <section className="px-qb-gutter pt-[clamp(36px,6vw,58px)] pb-[30px] text-center">
      <div className="mx-auto flex max-w-[1000px] flex-col items-center gap-3.5">
        <h1 className="text-[clamp(24px,5vw,48px)] leading-[1.05] font-semibold text-qb-black">
          {t('home.hero.title_start', 'اعثر على أي شيء')}{' '}
          <span className="text-qb-brand">{t('home.hero.title_accent', 'قريب')}</span>{' '}
          {t('home.hero.title_end', 'منك')}
        </h1>
        <p className="max-w-[760px] text-[clamp(14px,2.2vw,24px)] text-qb-ink-muted">
          {t('home.hero.tagline', 'بيع واشترِ محلياً — سيارات، شقق، إلكترونيات، أثاث والمزيد. يثق بنا الملايين.')}
        </p>
      </div>
      <HomeSearchBar />
    </section>
  );
}

function FindPlaces() {
  return (
    // Skipped until it nears the viewport, so the script fonts of the heading are not fetched during page load.
    <section
      aria-labelledby="home-places"
      className="mx-auto max-w-[1440px] px-qb-gutter pt-[30px] text-center [contain-intrinsic-size:auto_560px] [content-visibility:auto]"
    >
      <h2 id="home-places" className="mb-9 text-[clamp(32px,5vw,56px)] leading-none font-semibold text-qb-icon">
        {t('home.places.title_start', 'استكشف الأماكن')}
        <br />
        {t('home.places.title_middle', 'حول')}{' '}
        {/* The reference sets this word in Story Script once the heading reaches 36 px (720 px wide) and in Dancing Script below.
            Arabic has neither script, so it keeps the heading's weight there. */}
        <span className="font-qb-script-compact font-bold text-qb-brand italic rtl:font-semibold rtl:not-italic min-[720px]:font-qb-script min-[720px]:text-[64px] min-[720px]:leading-[64px] min-[720px]:font-normal min-[720px]:not-italic">
          {t('home.places.title_accent', 'موقعك')}
        </span>
      </h2>
      <HomeCityTags />
      <div aria-hidden="true" className="mt-[34px] h-[22px] bg-qb-brand" />
    </section>
  );
}
