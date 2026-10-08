'use client';

/**
 * Ad detail page on the new design (88:776 / 547:39923 / 623:29490).
 *
 *  - 1440: photos and the content panels on the start side, the seller and
 *    ad-facts panels beside them from the top.
 *  - 744: photos across the page, then the two columns.
 *  - 390: one column with full-bleed photos and no breadcrumb.
 *
 * Under the panels: the seller's other ads (the design's "Another Ads From
 * Seller") and the similar ads.
 */
import { AdDescription } from '@/components/ads/AdDescription';
import { AdFactsCard } from '@/components/ads/AdFactsCard';
import { AdGallery } from '@/components/ads/AdGallery';
import { AdLocationCard } from '@/components/ads/AdLocationCard';
import { AdOverviewCard } from '@/components/ads/AdOverviewCard';
import { AdSellerAds, AdSimilarAds } from '@/components/ads/AdRelatedAds';
import { AdSellerCard } from '@/components/ads/AdSellerCard';
import { AdSpecs } from '@/components/ads/AdSpecs';
import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { pageFrame } from '@/components/design-system/page-frame';
import { useAuth } from '@/hooks/useAuth';
import { localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { Ad } from '@/lib/api/types';

const CRUMB_TITLE_LENGTH = 24;

/** The design ends the breadcrumb with the first words of the title. */
function crumbTitle(title: string): string {
  const characters = Array.from(title);
  return characters.length > CRUMB_TITLE_LENGTH ? `${characters.slice(0, CRUMB_TITLE_LENGTH).join('').trimEnd()}…` : title;
}

const favoriteClassName =
  'size-[42px] bg-qb-surface text-qb-ink ring-0 shadow-none backdrop-blur-none hover:text-qb-brand focus-visible:ring-qb-brand-active [&_svg]:size-6 qb-desktop:size-[45px] qb-desktop:[&_svg]:size-[26px]';

interface AdDetailViewProps {
  ad: Ad;
  locale: Locale;
}

export function AdDetailView({ ad, locale }: AdDetailViewProps) {
  const { user, isAuthenticated, isHydrated } = useAuth();
  const isOwner = isHydrated && isAuthenticated && user?.id === ad.user_id;
  const categoryName = ad.category ? localized(ad.category.name, locale) : '';
  const locationName = ad.location ? localized(ad.location.name, locale) : '';

  const crumbs = [
    { label: t('home.breadcrumb'), href: '/' },
    ...(ad.category ? [{ label: categoryName, href: `/c/${ad.category.slug}` }] : []),
    { label: crumbTitle(ad.title) },
  ];

  return (
    <main className="bg-qb-page pb-16 font-qb text-qb-ink">
      <div className={`${pageFrame} qb-tablet:pt-[72px] qb-desktop:pt-[65px]`}>
        <Breadcrumb items={crumbs} className="hidden qb-tablet:block" />

        <div className="[display:grid] grid-cols-1 gap-4 qb-tablet:mt-[49px] qb-tablet:grid-cols-[minmax(0,1fr)_263px] qb-tablet:gap-x-[17px] qb-tablet:gap-y-6 qb-desktop:grid-cols-[minmax(0,1fr)_421px] qb-desktop:gap-x-8">
          <AdGallery
            images={ad.images ?? []}
            alt={ad.title}
            favorite={isOwner ? null : <FavoriteButton adId={ad.id} className={favoriteClassName} />}
            className="-mx-4 qb-tablet:col-span-2 qb-tablet:mx-0 qb-desktop:col-span-1"
          />

          <div className="flex min-w-0 flex-col gap-4 qb-tablet:col-start-1 qb-tablet:row-start-2 qb-desktop:gap-6">
            <AdOverviewCard ad={ad} locale={locale} />
            {ad.description ? <AdDescription text={ad.description} /> : null}
            <AdSpecs ad={ad} locale={locale} />
          </div>

          <aside className="flex min-w-0 flex-col gap-4 self-start qb-tablet:col-start-2 qb-tablet:row-start-2 qb-desktop:[grid-row:1/span_2] qb-desktop:gap-6">
            <AdSellerCard ad={ad} seller={ad.user} isOwner={isOwner} locale={locale} />
            <AdFactsCard ad={ad} isOwner={isOwner} locale={locale} />
            {locationName ? (
              <AdLocationCard locationName={locationName} latitude={ad.latitude} longitude={ad.longitude} />
            ) : null}
          </aside>
        </div>

        <AdSellerAds adId={ad.id} sellerId={ad.user_id} />
        <AdSimilarAds adId={ad.id} categorySlug={ad.category?.slug} />
      </div>
    </main>
  );
}
