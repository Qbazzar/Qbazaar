'use client';

/** The first eight main categories of the home feed as the "Browse Categories" tiles. */
import Link from 'next/link';

import { focusRing } from '@/components/design-system/focus-ring';
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
  const { data, isLoading, isError } = useHomeFeedQuery();

  if (isLoading) {
    return (
      <div className={grid} aria-busy="true">
        {Array.from({ length: TILE_COUNT }, (_, i) => (
          <div key={i} className={cn(tile, 'h-[170px] animate-pulse')} aria-hidden="true" />
        ))}
      </div>
    );
  }
  if (isError || !data) {
    return (
      <p className="py-8 text-center text-qb-caption text-qb-ink-subtle">
        {t('categories.errors.not_found', 'تعذّر تحميل الأقسام')}
      </p>
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
