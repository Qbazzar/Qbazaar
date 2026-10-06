'use client';

import { useId, useState, type FormEvent } from 'react';
import { Search } from 'lucide-react';

import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { catalogContainer, catalogPageTop, headingFont } from '@/components/catalog/layout';
import { ListingResults } from '@/components/catalog/ListingResults';
import { t } from '@/lib/i18n/messages';
import { useFeaturedAdsQuery } from '@/lib/queries/ads';
import { cn } from '@/lib/utils';

interface SearchNotFoundProps {
  query: string;
  breadcrumb: BreadcrumbItem[];
  onSearch: (query: string) => void;
  /** Clears the filters; omitted when none is set. */
  onReset?: () => void;
}

const RECOMMENDED = 5;

/** "Search Not Found" with a new search box and recommended ads (655:55973, 655:55238, 654:51147). */
export function SearchNotFound({ query, breadcrumb, onSearch, onReset }: SearchNotFoundProps) {
  const id = useId();
  const [value, setValue] = useState(query);
  const recommended = useFeaturedAdsQuery();
  const recommendedId = `${id}-recommended`;

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    onSearch(value.trim());
  };

  return (
    <main className={cn('bg-qb-page font-qb text-qb-ink', headingFont)}>
      <div className={cn(catalogContainer, catalogPageTop)}>
        <Breadcrumb items={breadcrumb} className="mb-[68px] hidden qb-tablet:block qb-desktop:mb-[83px]" />
        <h1 className="sr-only">{t('catalog.not_found.heading', 'لا توجد نتائج')}</h1>

        <form role="search" onSubmit={submit} className="relative mb-6 max-w-[233px] qb-tablet:max-w-[274px] qb-desktop:mb-8 qb-desktop:max-w-[562px]">
          <label htmlFor={`${id}-q`} className="sr-only">
            {t('search.placeholder', 'ابحث عن سيارة، شقة، هاتف…')}
          </label>
          <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 start-4 flex items-center text-qb-ink-subtle qb-desktop:start-5">
            <Icon icon={Search} className="qb-desktop:size-6" />
          </span>
          <input
            id={`${id}-q`}
            type="search"
            enterKeyHint="search"
            value={value}
            onChange={(event) => setValue(event.target.value)}
            placeholder={t('search.submit', 'بحث')}
            className="h-11 w-full rounded-qb-lg border border-qb-line bg-qb-surface ps-11 pe-4 text-qb-caption text-qb-ink outline-none placeholder:text-qb-placeholder focus-visible:border-qb-brand focus-visible:ring-2 focus-visible:ring-qb-brand/20 qb-tablet:h-12 qb-tablet:rounded-qb-xl qb-desktop:h-14 qb-desktop:ps-12 qb-desktop:text-qb-h5 qb-desktop:shadow-qb-card [&::-webkit-search-cancel-button]:appearance-none"
          />
        </form>

        <div className="flex min-h-[324px] items-center justify-center rounded-qb-2xl border border-qb-line bg-qb-surface shadow-qb-card qb-tablet:min-h-[512px] qb-desktop:min-h-[415px]">
          <EmptyState
            icon={<Icon icon={Search} size="lg" className="size-[42px]" />}
            title={t('catalog.not_found.title', 'لا توجد نتائج')}
            description={t('catalog.not_found.text', 'لم نعثر على أي شيء يطابق بحثك.')}
            action={
              onReset ? (
                <Button variant="secondary" size="sm" onClick={onReset}>
                  {t('search.reset_filters', 'إعادة ضبط الفلاتر')}
                </Button>
              ) : undefined
            }
          />
        </div>

        {recommended.data?.length ? (
          <section aria-labelledby={recommendedId} className="mt-9 qb-tablet:mt-[33px] qb-desktop:mt-8">
            <h2 id={recommendedId} className="mb-11 font-qb text-qb-body leading-none font-semibold tracking-normal text-qb-ink-title qb-tablet:mb-[38px] qb-tablet:text-qb-h2 qb-tablet:text-qb-ink qb-desktop:mb-[46px]">
              {t('catalog.not_found.recommended', 'مقترحة لك')}
            </h2>
            <ListingResults
              ads={recommended.data.slice(0, RECOMMENDED)}
              view="list"
              isLoading={false}
              label={t('catalog.not_found.recommended', 'مقترحة لك')}
              empty={null}
            />
          </section>
        ) : null}
      </div>
    </main>
  );
}
