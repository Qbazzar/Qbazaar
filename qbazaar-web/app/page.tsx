import Link from 'next/link';
import { HomeCategoryStrip } from '@/components/home/HomeCategoryStrip';
import { HomeCityTags } from '@/components/home/HomeCityTags';
import { HomeFeaturedAds } from '@/components/home/HomeFeaturedAds';
import { HomeLatestAds } from '@/components/home/HomeLatestAds';
import { RecentlyViewedStrip } from '@/components/account/RecentlyViewedStrip';
import { HomeSearchBar } from '@/components/home/HomeSearchBar';
import { t } from '@/lib/i18n/messages';

/**
 * Homepage — QBFront port (source: QBFront/index.html + QBFront/ar/index.html).
 *
 * Hero → category grid → featured ads → recently viewed → latest ads →
 * find-places word cloud. Each data-driven section is a client island so
 * we keep TanStack Query + Echo wiring untouched.
 */
export default function HomePage() {
  return (
    <main>
      {/* HERO */}
      <section className="hero">
        <div className="hero__inner container">
          <h1 className="hero__title">
            {t('home.hero.headline_a', 'اعثر على أي شيء')}{' '}
            <span className="text-accent">
              {t('home.hero.headline_b', 'قريباً منك')}
            </span>
          </h1>
          <p className="hero__sub">
            {t(
              'home.hero.subtitle',
              'بيع واشترِ في قطر — سيارات، عقارات، إلكترونيات، أثاث والمزيد. بثقة من الآلاف.',
            )}
          </p>
          <HomeSearchBar />
        </div>
      </section>

      {/* BROWSE CATEGORIES */}
      <section className="container" style={{ paddingTop: 48 }}>
        <div className="section-header">
          <div>
            <h2 className="section-header__title">
              {t('home.sections.top_categories_title', 'تصفّح الأقسام')}
            </h2>
            <p className="section-header__sub">
              {t('home.sections.top_categories_sub', 'أهم ما يُعرض في منطقتك')}
            </p>
          </div>
          <Link href="/categories" className="section-header__action">
            {t('home.sections.view_more', 'عرض الكل')}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </Link>
        </div>
        <HomeCategoryStrip />
      </section>

      {/* FEATURED ADS — auto-hidden when empty */}
      <HomeFeaturedAds />

      {/* RECENTLY VIEWED — auth-only, auto-hidden when empty */}
      <section className="container" style={{ paddingTop: 48 }}>
        <RecentlyViewedStrip />
      </section>

      {/* LATEST ADS */}
      <section className="container" style={{ paddingTop: 48 }}>
        <div className="section-header">
          <div>
            <h2 className="section-header__title">
              {t('home.sections.latest_ads_title', 'أحدث الإعلانات')}
            </h2>
            <p className="section-header__sub">
              {t('home.sections.latest_ads_sub', 'إعلانات منشورة في آخر 24 ساعة')}
            </p>
          </div>
          <Link href="/ads" className="section-header__action">
            {t('home.sections.view_more', 'عرض الكل')}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </Link>
        </div>
        <HomeLatestAds />
        <div style={{ display: 'flex', justifyContent: 'center', marginTop: 24 }}>
          <Link href="/ads" className="btn btn--primary btn--pill">
            {t('ads.list.load_more', 'تحميل المزيد')}
          </Link>
        </div>
      </section>

      {/* FIND PLACES */}
      <section className="find-places container">
        <h2 className="find-places__title">
          {t('home.find_places.title', 'استكشف الأماكن')}
        </h2>
        <div className="find-places__title">
          {t('home.find_places.subtitle_a', 'حول')}{' '}
          <em>{t('home.find_places.subtitle_b', 'موقعك')}</em>
        </div>
        <div style={{ marginTop: 28 }}>
          <Link href="/ads" className="btn btn--primary btn--pill">
            {t('home.sections.view_more', 'عرض الكل')}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </Link>
        </div>
        <HomeCityTags />
      </section>
    </main>
  );
}
