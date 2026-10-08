import { siteFrame } from '@/components/design-system/site-frame';
import { SectionHeader } from '@/components/design-system/SectionHeader';
import { HomeCategoryStrip } from '@/components/home/HomeCategoryStrip';
import { HomeCityTags } from '@/components/home/HomeCityTags';
import { HomeFeaturedCompanies } from '@/components/home/HomeFeaturedCompanies';
import { HomeFeedAds } from '@/components/home/HomeFeedAds';
import { HomeRecentlyViewed } from '@/components/home/HomeRecentlyViewed';
import { HomeSearchBar } from '@/components/home/HomeSearchBar';
import { QatarPlacesProvider } from '@/components/locations/QatarPlacesProvider';
import type { HomeFeed } from '@/lib/api/home';
import type { Location } from '@/lib/api/types';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { fetchApiData } from '@/lib/seo';
import { cn } from '@/lib/utils';

/** The anonymous home feed of the page language and the locations tree; undefined where the API failed. */
async function fetchHomeData(locale: Locale) {
  const [feed, places] = await Promise.all([
    // The API rebuilds the feed every two minutes, so the cached copy is shared that long.
    fetchApiData<HomeFeed>(`/api/v1/home?lang=${locale}`, 120),
    // The card places and the collage read the locations tree, which rarely changes.
    fetchApiData<Location[]>('/api/v1/locations/qatar'),
  ]);
  return { feed: feed ?? undefined, places: places ?? undefined };
}

/**
 * Home page on the new design (728:44663 / 561:22024 / 584:25249). The page
 * is a server component; each data-driven section is a client island reading
 * the one cached home feed query, seeded with the copy fetched here so the
 * first HTML carries the categories, ads, companies and places.
 */
export default async function HomePage() {
  // Pages render in parallel with the root layout, so prime the request
  // locale here too before the server-side t() calls below.
  const locale = await resolveServerLocale();
  const { feed, places } = await fetchHomeData(locale);
  return (
    <QatarPlacesProvider places={places}>
      <main className="bg-qb-page font-qb text-qb-ink">
        <HomeHero />
        <section aria-labelledby="home-categories" className={cn(siteFrame, 'py-5')}>
          <SectionHeader
            id="home-categories"
            title={t('home.sections.categories_title', 'تصفّح الأقسام')}
            subtitle={t('home.sections.categories_sub', 'أهم ما يُعرض في منطقتك')}
            action={{ href: '/categories', label: t('home.sections.view_all', 'عرض الكل') }}
            className="mb-[22px]"
          />
          <HomeCategoryStrip initialFeed={feed} />
        </section>
        <HomeFeedAds
          list="recommended"
          id="home-recommended"
          title={t('home.sections.recommended_title', 'مختارة لك')}
          subtitle={t('home.sections.recommended_sub', 'عروض منتقاة قريبة منك')}
          eager
          initialFeed={feed}
        />
        <HomeRecentlyViewed />
        <HomeFeaturedCompanies initialFeed={feed} />
        <HomeFeedAds
          list="best_selling"
          id="home-best-selling"
          title={t('home.sections.best_selling_title', 'الأكثر مبيعاً')}
          subtitle={t('home.sections.best_selling_sub', 'منتجات رائجة يحبها الناس.')}
          className="pb-10"
          initialFeed={feed}
        />
        <FindPlaces />
      </main>
    </QatarPlacesProvider>
  );
}

// The spaces live inside the text nodes: a separate {' '} child sits between React's
// comment markers, and Chrome then drops it from the heading's accessible name.

function HomeHero() {
  return (
    <section className="px-qb-gutter pt-[clamp(36px,6vw,58px)] pb-[30px] text-center">
      <div className="mx-auto flex max-w-[1000px] flex-col items-center gap-3.5">
        <h1 className="text-[clamp(24px,5vw,48px)] leading-[1.05] font-semibold text-qb-black">
          {`${t('home.hero.title_start', 'اعثر على أي شيء')} `}
          <span className="text-qb-brand">{t('home.hero.title_accent', 'قريب')}</span>
          {` ${t('home.hero.title_end', 'منك')}`}
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
    <section aria-labelledby="home-places" className={cn(siteFrame, 'pt-[30px] text-center')}>
      <h2 id="home-places" className="mb-9 text-[clamp(32px,5vw,56px)] leading-none font-semibold text-qb-icon">
        {t('home.places.title_start', 'استكشف الأماكن')}
        <br />
        {`${t('home.places.title_middle', 'حول')} `}
        {/* The reference sets this word in Story Script once the heading reaches 36 px (720 px wide) and in Dancing
            Script below, enlarged to make up for their small letters. Arabic has neither script, so the word keeps
            the heading's face, size and weight there. */}
        <span className="text-qb-brand ltr:font-qb-script-compact ltr:font-bold ltr:italic ltr:min-[720px]:font-qb-script ltr:min-[720px]:text-[64px] ltr:min-[720px]:leading-[64px] ltr:min-[720px]:font-normal ltr:min-[720px]:not-italic">
          {t('home.places.title_accent', 'موقعك')}
        </span>
      </h2>
      <HomeCityTags />
      <div aria-hidden="true" className="mt-[34px] h-[22px] bg-qb-brand" />
    </section>
  );
}
