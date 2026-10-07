import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { CatalogHeader } from './CatalogHeader';
import { CatalogLayout } from './CatalogLayout';

const block = 'animate-pulse rounded-qb-2xl border border-qb-line bg-qb-surface motion-reduce:animate-none';

/**
 * Loading state of the listing pages: the same frame with placeholders, so
 * nothing jumps. Without a title (category not resolved yet) the title shimmers.
 */
export function CatalogPageSkeleton({ title }: { title?: string }) {
  const loading = t('common.loading', 'جاري التحميل…');
  return (
    <CatalogLayout
      header={
        <CatalogHeader
          title={
            title ?? (
              <span className="inline-block h-[1em] w-48 animate-pulse rounded-qb-sm bg-qb-line motion-reduce:animate-none">
                <span className="sr-only">{loading}</span>
              </span>
            )
          }
        />
      }
      sidebar={<div aria-hidden="true" className={cn(block, 'hidden h-[640px] w-[356px] shrink-0 qb-desktop:block')} />}
    >
      <div aria-busy="true" className="flex flex-col gap-4 qb-tablet:gap-6">
        <span className="sr-only">{loading}</span>
        {Array.from({ length: 3 }, (_, index) => (
          <div key={index} aria-hidden="true" className={cn(block, 'h-[270px] qb-desktop:h-[194px]')} />
        ))}
      </div>
    </CatalogLayout>
  );
}
