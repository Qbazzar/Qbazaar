'use client';

/** The first eight main categories of the home feed as the "Browse Categories" tiles. */
import Link from 'next/link';
import { CircleAlert } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { formatNumber } from '@/lib/i18n/format';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { useHomeFeedQuery } from '@/lib/queries/home';
import { cn } from '@/lib/utils';

const TILE_COUNT = 8;
// `[display:grid]` because qbfront.css still forces a 20px gap on the `.grid` class.
const grid =
  '[display:grid] grid-cols-2 gap-3 qb-tablet:grid-cols-4 qb-tablet:gap-3.5 qb-desktop:grid-cols-[repeat(auto-fill,minmax(300px,1fr))] qb-desktop:gap-5';
const tile = 'flex flex-col gap-[22px] rounded-qb-xl border border-qb-line bg-qb-surface p-[22px]';

export function HomeCategoryStrip() {
  const locale = getLocale();
  const { data, isLoading, isError, refetch } = useHomeFeedQuery();

  if (isLoading) {
    return (
      <div className={grid} aria-busy="true">
        {Array.from({ length: TILE_COUNT }, (_, i) => (
          <div key={i} className={cn(tile, 'h-[170px] animate-pulse')} aria-hidden="true" />
        ))}
      </div>
    );
  }
  // The other home sections hide when the feed fails, so this one says so and retries the whole feed.
  if (isError || !data) {
    return (
      <div role="alert" className="rounded-qb-2xl border border-qb-line bg-qb-surface">
        <EmptyState
          icon={<Icon icon={CircleAlert} size="lg" />}
          title={t('common.error', 'حدث خطأ، حاول مرة أخرى')}
          headingLevel="h3"
          action={
            <Button variant="secondary" size="sm" onClick={() => refetch()}>
              {t('common.retry', 'إعادة المحاولة')}
            </Button>
          }
        />
      </div>
    );
  }

  return (
    <ul className={grid}>
      {data.categories.slice(0, TILE_COUNT).map((cat) => (
        <li key={cat.id}>
          <Link
            href={`/c/${cat.slug}`}
            className={cn(
              tile,
              'h-full transition-[box-shadow,translate] duration-200 hover:-translate-y-[3px] hover:shadow-qb-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0',
              focusRing,
            )}
          >
            <span aria-hidden="true" className="flex size-12 items-center justify-center rounded-qb-lg bg-qb-brand-soft text-qb-brand">
              <DynamicIcon name={cat.icon} className="size-6" />
            </span>
            <span>
              <span className="mb-1.5 block text-qb-body-lg font-medium text-qb-ink">{localized(cat.name, locale)}</span>
              <span className="block text-qb-caption text-qb-ink-subtle">
                {t(
                  'categories.ads_count',
                  { count: formatNumber(cat.ads_count, locale) },
                  `${formatNumber(cat.ads_count, locale)} إعلان`,
                )}
              </span>
            </span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
