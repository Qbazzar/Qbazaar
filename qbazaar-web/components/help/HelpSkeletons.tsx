import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** Grid of the help topic tiles: 2 / 3 / 4 columns like the all-categories frames. */
export const HELP_TOPIC_GRID =
  'grid grid-cols-2 gap-2 qb-tablet:grid-cols-3 qb-tablet:gap-3 qb-desktop:grid-cols-4 qb-desktop:gap-4';

const pulse = 'animate-pulse rounded-qb-sm bg-qb-fill-strong motion-reduce:animate-none';

/** A grey bar standing in for a block (an icon, a line) while it loads. */
export function SkeletonLine({ className }: { className?: string }) {
  return <span aria-hidden="true" className={cn('block', pulse, className)} />;
}

/** A grey bar inside a line of text: the space keeps the line's real height, so nothing moves when the text arrives. */
export function SkeletonText({ className }: { className?: string }) {
  return (
    <span aria-hidden="true" className={cn('inline-block max-w-full align-middle', pulse, className)}>
      {'\u00a0'}
    </span>
  );
}

function LoadingStatus() {
  return (
    <span role="status" className="sr-only">
      {t('common.loading')}
    </span>
  );
}

/** Placeholder tiles with the size of the real ones, so the grid does not jump when topics arrive. */
export function HelpTopicsSkeleton({ count = 8 }: { count?: number }) {
  return (
    <div>
      <LoadingStatus />
      <ul aria-hidden="true" className={HELP_TOPIC_GRID}>
        {Array.from({ length: count }, (_, index) => (
          <li
            key={index}
            className="flex min-h-[120px] flex-col rounded-qb-xl bg-qb-surface px-[17px] pt-[18px] shadow-qb-card qb-tablet:min-h-[148px] qb-tablet:rounded-qb-2xl qb-tablet:pt-5"
          >
            <SkeletonLine className="size-9 rounded-qb-md qb-tablet:size-[43px]" />
            <SkeletonLine className="mt-3 h-4 w-3/4 qb-tablet:mt-5" />
            <SkeletonLine className="mt-2.5 h-3 w-1/3" />
          </li>
        ))}
      </ul>
    </div>
  );
}

export function HelpRowsSkeleton({ count = 4 }: { count?: number }) {
  return (
    <div>
      <LoadingStatus />
      <ul aria-hidden="true" className="flex flex-col gap-4">
        {Array.from({ length: count }, (_, index) => (
          <li
            key={index}
            className="flex h-[60px] items-center rounded-qb-xl bg-qb-surface px-5 shadow-qb-card qb-tablet:h-[68px] qb-tablet:rounded-qb-2xl qb-tablet:px-6 qb-desktop:h-[78px]"
          >
            <SkeletonLine className="h-4 w-2/3 max-w-[480px]" />
          </li>
        ))}
      </ul>
    </div>
  );
}
