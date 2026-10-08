'use client';

import { CompanyCard } from '@/components/design-system/CompanyCard';
import { siteFrame } from '@/components/design-system/site-frame';
import { SectionHeader } from '@/components/design-system/SectionHeader';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { useHomeFeedQuery } from '@/lib/queries/home';
import { cn } from '@/lib/utils';

/** One row of five on desktop, three a row on tablets and the first two on phones, as in the design. */
const COMPANY_COUNT = 5;
const grid =
  '[display:grid] grid-cols-2 gap-3 qb-tablet:grid-cols-3 qb-tablet:gap-4 qb-desktop:grid-cols-5 qb-desktop:gap-5 max-[600px]:[&>*:nth-child(n+3)]:hidden';

/** "Featured Companies" of the home feed: the business sellers with the most live ads. */
export function HomeFeaturedCompanies() {
  const locale = getLocale();
  const { data, isLoading, isError } = useHomeFeedQuery();
  const sellers = data?.featured_sellers.slice(0, COMPANY_COUNT) ?? [];
  if ((!data && isError) || (!isLoading && sellers.length === 0)) return null;

  return (
    <section aria-labelledby="home-companies" className={cn(siteFrame, 'py-6')}>
      <SectionHeader
        id="home-companies"
        title={t('home.sections.companies_title', 'شركات مميزة')}
        subtitle={t('home.sections.companies_sub', 'اعثر على أعمال موثوقة قريبة منك.')}
        action={{ href: '/companies', label: t('home.sections.view_all', 'عرض الكل') }}
        className="mb-[22px]"
      />
      {isLoading ? (
        <div className={grid} aria-busy="true">
          {Array.from({ length: COMPANY_COUNT }, (_, i) => (
            <div key={i} aria-hidden="true" className="h-[180px] animate-pulse rounded-qb-2xl border border-qb-line bg-qb-surface motion-reduce:animate-none" />
          ))}
        </div>
      ) : (
        <ul className={grid}>
          {sellers.map((seller) => (
            <li key={seller.id}>
              <CompanyCard
                href={`/u/${seller.id}`}
                name={seller.full_name}
                logoUrl={seller.avatar_url}
                toneKey={seller.id}
                meta={seller.location ? localized(seller.location.name, locale) : undefined}
                count={tPlural('catalog.ads_count', seller.ads_count, locale)}
                className="h-full"
              />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
