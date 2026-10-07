import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

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

/** Placeholder article rows with the size of the real ones. */
export function HelpRowsSkeleton({ count = 4 }: { count?: number }) {
  return (
    <div>
      <span role="status" className="sr-only">
        {t('common.loading')}
      </span>
      <ul aria-hidden="true" className="flex flex-col gap-4">
        {Array.from({ length: count }, (_, index) => (
          <li
            key={index}
            className="flex h-[60px] items-center rounded-qb-xl border border-qb-line bg-qb-surface px-5 shadow-qb-card qb-tablet:h-[68px] qb-tablet:rounded-qb-2xl qb-tablet:px-6 qb-desktop:h-[78px]"
          >
            <SkeletonLine className="h-4 w-2/3 max-w-[480px]" />
          </li>
        ))}
      </ul>
    </div>
  );
}
