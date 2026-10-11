'use client';

/** The first eight main categories of the home feed as the "Browse Categories" tiles. */
import { useEffect, useRef } from 'react';
import Link from 'next/link';
import { CircleAlert } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { cardHover } from '@/components/design-system/card-hover';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { useRetry } from '@/hooks/useRetry';
import type { HomeFeed } from '@/lib/api/home';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { useHomeFeedQuery } from '@/lib/queries/home';
import { cn } from '@/lib/utils';

const TILE_COUNT = 8;
const grid =
  'grid grid-cols-2 gap-3 qb-tablet:grid-cols-4 qb-tablet:gap-3.5 qb-desktop:grid-cols-[repeat(auto-fill,minmax(300px,1fr))] qb-desktop:gap-5';
const tile = 'flex flex-col gap-[22px] rounded-qb-xl border border-qb-line bg-qb-surface p-[22px]';

export function HomeCategoryStrip({ initialFeed }: { initialFeed?: HomeFeed }) {
  const locale = getLocale();
  const { data, isError, refetch } = useHomeFeedQuery(initialFeed);
  const { retrying, retry } = useRetry(refetch);
  const firstTileRef = useRef<HTMLAnchorElement>(null);
  const focusTilesOnLoad = useRef(false);

  // A successful retry replaces the panel, and the Retry button with it, so the focus moves to the first tile.
  useEffect(() => {
    if (!focusTilesOnLoad.current) return;
    if (data) {
      focusTilesOnLoad.current = false;
      if (document.activeElement === document.body) firstTileRef.current?.focus();
    } else if (isError && !retrying) {
      focusTilesOnLoad.current = false;
    }
  }, [data, isError, retrying]);

  if (!data) {
    // The other home sections hide when the feed fails, so this one says so and retries the whole feed.
    if (isError || retrying) {
      return (
        <div role="alert" className="rounded-qb-2xl border border-qb-line bg-qb-surface">
          <EmptyState
            icon={<Icon icon={CircleAlert} size="lg" />}
            title={t('common.error', 'حدث خطأ، حاول مرة أخرى')}
            headingLevel="h3"
            action={
              // aria-disabled rather than disabled: a disabled button drops the keyboard focus it holds.
              <Button
                variant="secondary"
                size="sm"
                aria-disabled={retrying || undefined}
                onClick={
                  retrying
                    ? undefined
                    : () => {
                        focusTilesOnLoad.current = true;
                        void retry();
                      }
                }
              >
                {t('common.retry', 'إعادة المحاولة')}
              </Button>
            }
          />
        </div>
      );
    }
    return (
      <div className={grid} aria-busy="true">
        {Array.from({ length: TILE_COUNT }, (_, i) => (
          <div key={i} className={cn(tile, 'h-[170px] animate-pulse motion-reduce:animate-none')} aria-hidden="true" />
        ))}
      </div>
    );
  }

  return (
    <ul className={grid}>
      {data.categories.slice(0, TILE_COUNT).map((cat, index) => (
        <li key={cat.id}>
          <Link
            ref={index === 0 ? firstTileRef : undefined}
            href={`/c/${cat.slug}`}
            className={cn(
              tile,
              'h-full',
              cardHover,
              focusRing,
            )}
          >
            <span aria-hidden="true" className="flex size-12 items-center justify-center rounded-qb-lg bg-qb-brand-soft text-qb-brand">
              <DynamicIcon name={cat.icon} className="size-6" strokeWidth={1.6} />
            </span>
            <span>
              <span className="mb-1.5 block text-qb-body-lg font-medium text-qb-ink">{localized(cat.name, locale)}</span>
              <span className="block text-qb-caption text-qb-ink-subtle">{tPlural('catalog.ads_count', cat.ads_count, locale)}</span>
            </span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
