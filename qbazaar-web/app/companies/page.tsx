import type { Metadata } from 'next';
import Link from 'next/link';
import { Building2, Users } from 'lucide-react';

import { CompanySearchForm } from '@/components/companies/CompanySearchForm';
import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { buttonVariants } from '@/components/design-system/Button';
import { CompanyCard } from '@/components/design-system/CompanyCard';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Pagination } from '@/components/design-system/Pagination';
import { formatCount, tCount } from '@/lib/ads/display';
import type { Company } from '@/lib/api/types';
import { companiesApiPath, companiesHref, readCompaniesParams, type CompaniesParams } from '@/lib/companies/directory';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl, fetchApiPage } from '@/lib/seo';

interface PageProps {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}

/** The unfiltered pages are worth caching; a search is one visitor's question. */
const DIRECTORY_REVALIDATE_SECONDS = 300;

export async function generateMetadata({ searchParams }: PageProps): Promise<Metadata> {
  const [params] = await Promise.all([searchParams.then(readCompaniesParams), resolveServerLocale()]);
  return {
    title: t('companies.title'),
    description: t('companies.subtitle'),
    alternates: { canonical: absoluteUrl(companiesHref({ query: '', page: params.page })) },
    // Search results are thin, ever-changing pages: keep them out of the index.
    robots: params.query ? { index: false, follow: true } : undefined,
  };
}

/**
 * Companies directory — `/companies` (179:4492 / 532:31445 / 620:28373).
 * Business accounts, most followed first, 20 to a page, rendered on the
 * server: the search is a GET form and every page is a real link.
 */
export default async function CompaniesPage({ searchParams }: PageProps) {
  const params = readCompaniesParams(await searchParams);
  const [locale, result] = await Promise.all([
    resolveServerLocale(),
    fetchApiPage<Company>(companiesApiPath(params), params.query ? 0 : DIRECTORY_REVALIDATE_SECONDS),
  ]);
  const total = result?.meta.total ?? 0;
  const [heroBefore, heroAfter = ''] = t('companies.hero').split('{highlight}');

  return (
    <main className="bg-qb-page pb-16 font-qb text-qb-ink">
      <div className="mx-auto max-w-[1440px] px-4 pt-11 qb-tablet:px-6 qb-tablet:pt-[72px] qb-desktop:px-10 qb-desktop:pt-[65px]">
        <Breadcrumb
          items={[{ label: t('home.breadcrumb'), href: '/' }, { label: t('companies.title') }]}
          className="hidden qb-tablet:block"
        />

        <h1 className="text-qb-h2 leading-none font-semibold tracking-normal text-qb-ink qb-tablet:mt-[73px] qb-tablet:text-[32px] qb-desktop:mt-[49px] qb-desktop:text-qb-h1">
          {t('companies.title')}
        </h1>
        {result ? (
          <p className="mt-[19px] text-qb-caption text-qb-breadcrumb qb-tablet:mt-7 qb-tablet:text-qb-body qb-desktop:mt-[37px] qb-desktop:text-qb-h5">
            <span className="font-semibold text-qb-ink-body qb-tablet:font-medium">{formatCount(total, locale)}</span>{' '}
            {tCount('companies.count', total, locale)}
          </p>
        ) : null}

        <section aria-labelledby="companies-search-title" className="mt-[45px] qb-tablet:mt-[42px] qb-desktop:mt-[77px]">
          <div className="mx-auto max-w-[1109px] text-center">
            <h2
              id="companies-search-title"
              className="text-qb-h3 leading-none font-semibold tracking-normal text-qb-black qb-tablet:text-[36px] qb-desktop:text-qb-h1"
            >
              {heroBefore}
              <span className="text-qb-brand">{t('companies.hero_highlight')}</span>
              {heroAfter}
            </h2>
            <p className="mx-auto mt-2.5 max-w-[629px] text-qb-micro text-qb-ink-muted qb-tablet:mt-[26px] qb-tablet:text-qb-body qb-desktop:mt-10 qb-desktop:max-w-none qb-desktop:text-qb-h3 qb-desktop:leading-none">
              {t('companies.subtitle')}
            </p>
          </div>
          <div className="mx-auto mt-4 max-w-[1227px] qb-tablet:mt-8 qb-desktop:mt-11">
            <CompanySearchForm query={params.query} />
          </div>
        </section>

        <div className="mt-6 qb-tablet:mt-8 qb-desktop:mt-14">
          {result ? (
            <CompanyResults companies={result.data} params={params} lastPage={result.meta.last_page} locale={locale} />
          ) : (
            <EmptyState icon={<Icon icon={Building2} size="lg" />} title={t('companies.error_title')} description={t('companies.error_body')} />
          )}
        </div>
      </div>
    </main>
  );
}

interface CompanyResultsProps {
  companies: Company[];
  params: CompaniesParams;
  lastPage: number;
  locale: Locale;
}

function CompanyResults({ companies, params, lastPage, locale }: CompanyResultsProps) {
  if (companies.length === 0) {
    const filtered = Boolean(params.query) || params.page > 1;
    return (
      <EmptyState
        icon={<Icon icon={Building2} size="lg" />}
        title={params.query ? t('companies.no_results_title', { query: params.query }) : t('companies.empty_title')}
        description={params.query ? t('companies.no_results_body') : undefined}
        action={
          filtered ? (
            <Link href="/companies" className={buttonVariants({ variant: 'secondary' })}>
              {t('companies.show_all')}
            </Link>
          ) : undefined
        }
      />
    );
  }

  return (
    <>
      <ul className="[display:grid] grid-cols-2 gap-3 qb-tablet:grid-cols-3 qb-tablet:gap-4 qb-desktop:grid-cols-5 qb-desktop:gap-x-[27px] qb-desktop:gap-y-8">
        {companies.map((company) => (
          <li key={company.id}>
            <CompanyCard
              href={`/u/${company.id}`}
              name={company.business_name}
              logoUrl={company.avatar_url}
              toneKey={company.id}
              meta={tCount('companies.followers', company.followers_count, locale)}
              metaIcon={Users}
              count={tCount('companies.ads', company.active_ads_count, locale)}
              className="h-full"
            />
          </li>
        ))}
      </ul>
      <Pagination
        page={params.page}
        totalPages={lastPage}
        getHref={(page) => companiesHref({ ...params, page })}
        className="mt-6 qb-tablet:mt-8"
      />
    </>
  );
}
