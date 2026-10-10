import type { Metadata } from 'next';
import Link from 'next/link';
import { Building2 } from 'lucide-react';

import { CompanyDirectoryCard } from '@/components/companies/CompanyDirectoryCard';
import { CompanySearchForm } from '@/components/companies/CompanySearchForm';
import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Pagination } from '@/components/design-system/Pagination';
import type { Company } from '@/lib/api/types';
import { companiesApiPath, companiesHref, readCompaniesParams, type CompaniesParams } from '@/lib/companies/directory';
import { formatNumber } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { resolveServerLocale } from '@/lib/i18n/server';
import { pageMetadata } from '@/lib/page-metadata';
import { fetchApiPage } from '@/lib/seo';

interface PageProps {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}

/** The unfiltered pages are worth caching; a search is one visitor's question. */
const DIRECTORY_REVALIDATE_SECONDS = 300;

export async function generateMetadata({ searchParams }: PageProps): Promise<Metadata> {
  const [params] = await Promise.all([searchParams.then(readCompaniesParams), resolveServerLocale()]);
  return pageMetadata({
    title: t('companies.title'),
    description: t('companies.subtitle'),
    path: companiesHref({ query: '', page: params.page }),
    // Search results are thin, ever-changing pages: keep them out of the index.
    robots: params.query ? { index: false, follow: true } : undefined,
  });
}

/**
 * Companies directory — `/companies` (companies.html). Business accounts,
 * most followed first, 20 to a page, rendered on the server: the search is
 * a GET form and every page is a real link. The title, the centred
 * "Find the Right Companies" block and the search bar scale with the page
 * width as the reference's clamp() sizes do.
 */
export default async function CompaniesPage({ searchParams }: PageProps) {
  const params = readCompaniesParams(await searchParams);
  const [locale, result] = await Promise.all([
    resolveServerLocale(),
    fetchApiPage<Company>(companiesApiPath(params), params.query ? 0 : DIRECTORY_REVALIDATE_SECONDS),
  ]);
  const total = result?.meta.total ?? 0;

  return (
    <main className="bg-qb-page pb-16 font-qb text-qb-ink">
      <div className="mx-auto max-w-[1440px] px-qb-gutter pt-[clamp(20px,4vw,40px)]">
        <h1 className="text-[clamp(30px,5vw,48px)] font-semibold tracking-normal text-qb-ink">{t('companies.title')}</h1>
        {/* Present in every state, so a new search's count is read out when the results change in place. */}
        <p role="status" className="sr-only">
          {result ? `${formatNumber(total, locale)} ${tPlural('companies.count', total, locale)}` : ''}
        </p>

        <section aria-labelledby="companies-search-title" className="mt-[clamp(30px,6vw,70px)]">
          <div className="mx-auto max-w-[760px] text-center">
            <h2 id="companies-search-title" className="text-[clamp(28px,4vw,42px)] font-bold tracking-normal text-qb-ink">
              {t('companies.hero')}
            </h2>
            <p className="mt-4 text-qb-body-sm leading-[1.55] text-qb-ink-subtle qb-desktop:text-qb-body-lg">{t('companies.subtitle')}</p>
          </div>
          <div className="mt-[34px]">
            <CompanySearchForm query={params.query} />
          </div>
        </section>

        <div className="mt-10">
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
      <ul className="[display:grid] grid-cols-2 gap-3 qb-tablet:grid-cols-[repeat(auto-fill,minmax(200px,1fr))] qb-tablet:gap-[22px]">
        {companies.map((company) => (
          <li key={company.id}>
            <CompanyDirectoryCard
              href={`/u/${company.id}`}
              name={company.business_name}
              logoUrl={company.avatar_url}
              toneKey={company.id}
              meta={tPlural('companies.followers', company.followers_count, locale)}
              count={tPlural('companies.ads', company.active_ads_count, locale)}
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
