'use client';

import { useState, type FormEvent } from 'react';
import { usePathname, useRouter } from 'next/navigation';

import './catalog-tokens.css';

import { Button } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { SearchIcon } from '@/components/design-system/icons';
import { Pagination } from '@/components/design-system/Pagination';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { useMainCategoriesQuery } from '@/lib/queries/categories';

import { CatalogStats, todayStat } from './CatalogHeader';
import { CategoryCard } from './CategoryTile';
import { LoadError } from './LoadError';

/** Six rows of four on desktop. */
const PAGE_SIZE = 24;

/**
 * all-categories.html: auto-filled 280 px columns 22 px apart (four at 1440, one on phones), three
 * columns 14 px apart on tablets (responsive.css).
 */
const grid =
  '[display:grid] grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-[22px] qb-tablet:grid-cols-3 qb-tablet:gap-3.5 qb-desktop:grid-cols-[repeat(auto-fill,minmax(280px,1fr))] qb-desktop:gap-[22px]';

/** The listing a search of this page opens, as the reference's `doSearch` opens the category listing. */
export function listingSearchHref(query: string): string {
  const keyword = query.trim();
  return keyword ? `/search?${new URLSearchParams({ q: keyword })}` : '/search';
}

/** "10 Categories · +3 Ads Today" under the page title. */
export function CategoryIndexStats() {
  const locale = getLocale();
  const { data } = useMainCategoriesQuery();
  if (!data) return null;

  const today = data.reduce((sum, category) => sum + category.today_count, 0);
  return (
    <CatalogStats
      separator="·"
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
      {/* The reference boxes the form and leaves the input bare inside it; the box shows the keyboard focus. */}
      <form
        role="search"
        onSubmit={submit}
        className="mx-auto mt-7 mb-10 flex max-w-[820px] items-center gap-3 rounded-[14px] border border-qb-line bg-qb-surface p-1.5 ps-[18px] shadow-(--shadow-qb-search-box) has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-qb-brand-active has-[input:focus-visible]:outline-solid"
      >
        <label htmlFor="category-search" className="sr-only">
          {t('catalog.index.search_label', 'ابحث في الأقسام')}
        </label>
        <Icon icon={SearchIcon} size={20} className="text-qb-icon-faint" />
        <input
          id="category-search"
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t('catalog.index.search_placeholder', 'ابحث في الأقسام…')}
          autoComplete="off"
          className="w-full min-w-0 bg-transparent px-0.5 py-px font-qb text-qb-body text-qb-ink outline-none placeholder:text-qb-ink-muted [&::-webkit-search-cancel-button]:appearance-none"
        />
        <Button type="submit" className="h-auto rounded-qb-md px-[30px] py-3">
          {t('catalog.index.search_submit', 'بحث')}
        </Button>
      </form>

      {isLoading ? (
        <div aria-busy="true" className={grid}>
          {Array.from({ length: 8 }, (_, index) => (
            <div key={index} aria-hidden="true" className="h-[159px] animate-pulse rounded-qb-xl border border-qb-line bg-qb-surface motion-reduce:animate-none" />
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
          <Pagination page={page} totalPages={totalPages} getHref={pageHref} size="xl" className="mt-10" />
        </>
      )}
    </div>
  );
}
