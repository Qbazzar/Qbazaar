'use client';

import { useState, type FormEvent } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { Search } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { Input } from '@/components/design-system/Input';
import { Pagination } from '@/components/design-system/Pagination';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { useMainCategoriesQuery } from '@/lib/queries/categories';

import { CatalogStats, todayStat } from './CatalogHeader';
import { CategoryCard } from './CategoryTile';
import { LoadError } from './LoadError';

/** Six rows of four on desktop, as in 185:6576. */
const PAGE_SIZE = 24;

const grid =
  '[display:grid] grid-cols-2 gap-x-2 gap-y-3.5 qb-tablet:grid-cols-3 qb-tablet:gap-x-2.5 qb-tablet:gap-y-6 qb-desktop:grid-cols-4 qb-desktop:gap-x-4 qb-desktop:px-10';

/** The listing a search of this page opens, as the reference's `doSearch` opens the category listing. */
export function listingSearchHref(query: string): string {
  const keyword = query.trim();
  return keyword ? `/search?${new URLSearchParams({ q: keyword })}` : '/search';
}

/** "10 Categories • +3 Ads Today" under the page title. */
export function CategoryIndexStats() {
  const locale = getLocale();
  const { data } = useMainCategoriesQuery();
  if (!data) return null;

  const today = data.reduce((sum, category) => sum + category.today_count, 0);
  return (
    <CatalogStats
      items={[
        { value: formatNumber(data.length, locale), label: tPlural('catalog.stats.categories', data.length) },
        ...todayStat(today, tPlural('catalog.stats.ads_today', today), locale),
      ]}
    />
  );
}

/**
 * Search box, category cards and pagination of `/categories`. Searching opens
 * the ad listing for the words (all-categories.html). The page comes from the
 * server, so this needs no Suspense boundary and hydrates together with the
 * counters above it, which share its query.
 */
export function CategoryIndex({ page: requestedPage }: { page: number }) {
  const locale = getLocale();
  const router = useRouter();
  const pathname = usePathname();
  const { data, isLoading, isError, refetch } = useMainCategoriesQuery();
  const [query, setQuery] = useState('');

  const categories = data ?? [];
  const totalPages = Math.max(1, Math.ceil(categories.length / PAGE_SIZE));
  const page = Math.min(requestedPage, totalPages);
  const visible = categories.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  const pageHref = (target: number) => (target > 1 ? `${pathname}?page=${target}` : pathname);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    router.push(listingSearchHref(query));
  };

  return (
    <div className="font-qb">
      <form
        role="search"
        onSubmit={submit}
        className="relative mx-auto mb-12 max-w-[1125px] qb-tablet:mb-8 qb-desktop:mb-12"
      >
        <label htmlFor="category-search" className="sr-only">
          {t('catalog.index.search_label', 'ابحث في الأقسام')}
        </label>
        <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 start-4 flex items-center text-qb-ink-subtle qb-tablet:start-5">
          <Icon icon={Search} className="size-5 qb-tablet:size-6" />
        </span>
        <Input
          id="category-search"
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t('catalog.index.search_placeholder', 'ابحث في الأقسام…')}
          autoComplete="off"
          className="h-11 rounded-[14px] ps-11 pe-4 text-qb-caption shadow-qb-card qb-tablet:h-14 qb-tablet:rounded-qb-xl qb-tablet:ps-14 qb-tablet:pe-32 qb-tablet:text-qb-body qb-desktop:text-qb-h5 [&::-webkit-search-cancel-button]:appearance-none"
        />
        <Button type="submit" size="sm" className="absolute inset-y-2 end-2 hidden h-auto rounded-qb-sm px-[30px] text-qb-body qb-tablet:inline-flex">
          {t('catalog.index.search_submit', 'بحث')}
        </Button>
      </form>

      {isLoading ? (
        <div aria-busy="true" className={grid}>
          {Array.from({ length: 8 }, (_, index) => (
            <div key={index} aria-hidden="true" className="h-[120px] animate-pulse rounded-qb-xl border border-qb-line bg-qb-surface motion-reduce:animate-none qb-tablet:h-[148px] qb-tablet:rounded-qb-2xl" />
          ))}
        </div>
      ) : isError || !data ? (
        <LoadError headingLevel="h3" title={t('common.error', 'حدث خطأ، حاول مرة أخرى')} onRetry={() => refetch()} />
      ) : (
        <>
          <ul className={grid}>
            {visible.map((category) => (
              <li key={category.id}>
                <CategoryCard
                  href={`/c/${category.slug}`}
                  name={localized(category.name, locale)}
                  icon={category.icon}
                  count={tPlural('catalog.ads_count', category.ads_count)}
                />
              </li>
            ))}
          </ul>
          <Pagination page={page} totalPages={totalPages} getHref={pageHref} className="mt-6 qb-desktop:mt-8" />
        </>
      )}
    </div>
  );
}
