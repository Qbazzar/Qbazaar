'use client';

import { useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { Search } from 'lucide-react';

import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Input } from '@/components/design-system/Input';
import { AdRow } from '@/components/catalog/AdRow';
import { catalogContainer, catalogPageTop, headingFont } from '@/components/catalog/layout';
import { useResultsFocusTarget } from '@/components/catalog/results-focus';
import { t } from '@/lib/i18n/messages';
import { useFeaturedAdsQuery } from '@/lib/queries/ads';
import { cn } from '@/lib/utils';

interface SearchNotFoundProps {
  query: string;
  breadcrumb: BreadcrumbItem[];
  /** A new search is running; a lost focus waits for its result before landing on the heading. */
  searching: boolean;
  onSearch: (query: string) => void;
  /** Clears the filters; omitted when none is set. */
  onReset?: () => void;
}

/** Recommended ads shown at first on desktop, and added by each "Load More Ads". */
const RECOMMENDED_STEP = 5;

/** "Search Not Found" with a new search box and recommended ads (655:55973, 655:55238, 654:51147). */
export function SearchNotFound({ query, breadcrumb, searching, onSearch, onReset }: SearchNotFoundProps) {
  const id = useId();
  // A query that finds nothing again leaves the focus in the box; a used "Reset filters" button is gone.
  const headingRef = useResultsFocusTarget<HTMLHeadingElement>({ ready: !searching, onlyIfFocusLost: true });
  const [value, setValue] = useState(query);
  const [shownQuery, setShownQuery] = useState(query);
  if (shownQuery !== query) {
    setShownQuery(query);
    setValue(query);
  }

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    onSearch(value.trim());
  };

  return (
    <main className={cn('bg-qb-page font-qb text-qb-ink', headingFont)}>
      <div className={cn(catalogContainer, catalogPageTop)}>
        <Breadcrumb items={breadcrumb} className="mb-[68px] hidden qb-tablet:block qb-desktop:mb-[83px]" />
        <h1 ref={headingRef} tabIndex={-1} className="sr-only">
          {t('catalog.not_found.heading', 'لا توجد نتائج')}
        </h1>

        <form role="search" onSubmit={submit} className="relative mb-6 max-w-[233px] qb-tablet:max-w-[274px] qb-desktop:mb-8 qb-desktop:max-w-[562px]">
          <label htmlFor={`${id}-q`} className="sr-only">
            {t('search.placeholder', 'ابحث عن سيارة، شقة، هاتف…')}
          </label>
          <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 start-4 flex items-center text-qb-ink-subtle qb-desktop:start-5">
            <Icon icon={Search} className="qb-desktop:size-6" />
          </span>
          <Input
            id={`${id}-q`}
            type="search"
            enterKeyHint="search"
            value={value}
            onChange={(event) => setValue(event.target.value)}
            placeholder={t('search.submit', 'بحث')}
            className="h-11 rounded-qb-lg ps-11 pe-4 text-qb-caption qb-tablet:h-12 qb-tablet:rounded-qb-xl qb-desktop:h-14 qb-desktop:ps-12 qb-desktop:text-qb-h5 qb-desktop:shadow-qb-card [&::-webkit-search-cancel-button]:appearance-none"
          />
        </form>

        <div className="flex min-h-[324px] items-center justify-center rounded-qb-2xl border border-qb-line bg-qb-surface shadow-qb-card qb-tablet:min-h-[512px] qb-desktop:min-h-[415px]">
          <EmptyState
            icon={<Icon icon={Search} size="lg" className="size-[42px]" />}
            title={t('catalog.not_found.title', 'لا توجد نتائج')}
            description={t('catalog.not_found.text', 'لم نعثر على أي شيء يطابق بحثك.')}
            className="max-qb-tablet:[&_h2]:text-qb-body max-qb-tablet:[&_p]:text-qb-caption"
            action={
              onReset ? (
                <Button variant="secondary" size="sm" onClick={onReset}>
                  {t('search.reset_filters', 'إعادة ضبط الفلاتر')}
                </Button>
              ) : undefined
            }
          />
        </div>

        <RecommendedAds />
      </div>
    </main>
  );
}

/** A swipeable row under 1001 px; list cards on desktop, five more per "Load More Ads". */
function RecommendedAds() {
  const id = useId();
  const recommended = useFeaturedAdsQuery();
  const [shown, setShown] = useState(RECOMMENDED_STEP);
  const listRef = useRef<HTMLUListElement>(null);
  const focusFrom = useRef<number | null>(null);

  // The focus goes to the first ad a click added: the button itself goes away with the last batch.
  useEffect(() => {
    const index = focusFrom.current;
    if (index === null) return;
    focusFrom.current = null;
    const links = listRef.current?.children[index]?.querySelectorAll<HTMLAnchorElement>('a') ?? [];
    Array.from(links)
      .find((link) => link.offsetParent !== null)
      ?.focus();
  }, [shown]);

  const ads = recommended.data ?? [];
  if (!ads.length) return null;

  const label = t('catalog.not_found.recommended', 'مقترحة لك');
  const headingId = `${id}-heading`;
  const showMore = () => {
    focusFrom.current = shown;
    setShown((count) => count + RECOMMENDED_STEP);
  };

  return (
    <section aria-labelledby={headingId} className="mt-9 qb-tablet:mt-[33px] qb-desktop:mt-8">
      <h2
        id={headingId}
        className="mb-11 font-qb text-qb-body leading-none font-semibold tracking-normal text-qb-ink-title qb-tablet:mb-[38px] qb-tablet:text-qb-h2 qb-tablet:text-qb-ink qb-desktop:mb-[46px]"
      >
        {label}
      </h2>
      <AdRow ref={listRef} ads={ads} label={label} desktop="list" desktopLimit={shown} />
      {ads.length > shown ? (
        <div className="mt-6 hidden justify-center qb-desktop:flex">
          <Button size="sm" onClick={showMore} className="h-[35px] rounded-qb-sm px-3 font-normal">
            {t('catalog.not_found.load_more', 'تحميل المزيد من الإعلانات')}
          </Button>
        </div>
      ) : null}
    </section>
  );
}
