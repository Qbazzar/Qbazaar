'use client';

/**
 * Ad detail page as product.html lays it out:
 *
 *  - from 601 px: the photos and the content panels on the start side, the
 *    seller and ad-facts panels beside them from the top, kept on screen
 *    while the content scrolls (position: sticky; top: 120px). The columns
 *    share the width 2 : 1 on desktop; the tablet sidebar is 260 px.
 *  - 390: one column, the seller panels after the content, no breadcrumb.
 *
 * Under the panels: the seller's other ads ("Another Ads From Seller") and
 * the similar ads in the same cards.
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
import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { useAuth } from '@/hooks/useAuth';
import { localized, type Locale } from '@/lib/i18n/locale';
import { isolate } from '@/lib/orders/text';
import { t } from '@/lib/i18n/messages';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { findCategoryPath } from '@/store/categories';
import type { Ad, Category, CategoryNode } from '@/lib/api/types';

const CRUMB_TITLE_LENGTH = 24;

/**
 * The design ends the breadcrumb with the first words of the title, isolated
 * so an English title keeps its order in Arabic ("24/7 plumber", not "plumber 24/7").
 */
function crumbTitle(title: string): string {
  const characters = Array.from(title);
  return isolate(characters.length > CRUMB_TITLE_LENGTH ? `${characters.slice(0, CRUMB_TITLE_LENGTH).join('').trimEnd()}…` : title);
}

/** Home > Car & Vehicles > Cars > title: the whole category chain once the tree is known. */
function adCrumbs(ad: Ad, tree: CategoryNode[] | undefined, locale: Locale): BreadcrumbItem[] {
  const chain: Pick<Category, 'name' | 'slug'>[] = ad.category ? (findCategoryPath(tree, ad.category.slug) ?? [ad.category]) : [];
  return [
    { label: t('home.breadcrumb'), href: '/' },
    ...chain.map((category) => ({ label: localized(category.name, locale), href: `/c/${category.slug}` })),
    { label: crumbTitle(ad.title) },
  ];
}

/** The reference's 40 px white heart on the photo: an 18 px grey outline, 16 px from the corner. */
const favoriteClassName = 'size-10 [&_svg]:size-[18px] [&_svg]:stroke-[1.6]';

interface AdDetailViewProps {
  ad: Ad;
  locale: Locale;
}

export function AdDetailView({ ad, locale }: AdDetailViewProps) {
  const { user, isAuthenticated, isHydrated } = useAuth();
  const { data: tree } = useCategoryTreeQuery();
  const isOwner = isHydrated && isAuthenticated && user?.id === ad.user_id;
  const locationName = ad.location ? localized(ad.location.name, locale) : '';

  return (
    <main className="bg-qb-page pb-16 font-qb text-qb-ink">
      <div className="mx-auto max-w-[1440px] px-qb-gutter pt-[clamp(20px,4vw,40px)]">
        <Breadcrumb items={adCrumbs(ad, tree, locale)} className="mb-[18px] hidden qb-tablet:block" />

        <div className="flex flex-col gap-6 qb-tablet:flex-row qb-tablet:items-start">
          <div className="flex min-w-0 flex-col gap-6 qb-tablet:flex-1 qb-desktop:flex-[2_1_560px]">
            <AdGallery
              images={ad.images ?? []}
              alt={ad.title}
              favorite={isOwner ? null : <FavoriteButton adId={ad.id} className={favoriteClassName} />}
            />
            <AdOverviewCard ad={ad} locale={locale} />
            {ad.description ? <AdDescription text={ad.description} /> : null}
            <AdSpecs ad={ad} locale={locale} />
            {locationName ? (
              <AdLocationCard locationName={locationName} latitude={ad.latitude} longitude={ad.longitude} />
            ) : null}
          </div>

          <aside className="flex min-w-0 flex-col gap-5 qb-tablet:sticky qb-tablet:top-[120px] qb-tablet:w-[260px] qb-tablet:shrink-0 qb-desktop:w-auto qb-desktop:flex-[1_1_300px]">
            <AdSellerCard ad={ad} seller={ad.user} isOwner={isOwner} locale={locale} />
            <AdFactsCard ad={ad} isOwner={isOwner} />
          </aside>
        </div>

        <AdSellerAds adId={ad.id} sellerId={ad.user_id} />
        <AdSimilarAds adId={ad.id} categorySlug={ad.category?.slug} />
      </div>
    </main>
  );
}
