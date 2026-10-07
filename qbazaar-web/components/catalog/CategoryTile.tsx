import Link from 'next/link';

import { focusRing } from '@/components/design-system/focus-ring';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { cn } from '@/lib/utils';

interface CategoryTileProps {
  href: string;
  name: string;
  /** Already formatted, e.g. "42,850 Ads". */
  count: string;
  /** Lucide icon name from the API. */
  icon: string | null;
  className?: string;
}

const tileBase = cn(
  'flex rounded-qb-xl border border-qb-line bg-qb-surface font-qb shadow-qb-card transition-shadow duration-200 hover:shadow-qb-hover motion-reduce:transition-none',
  focusRing,
);

function IconBox({ icon, className }: { icon: string | null; className?: string }) {
  return (
    <span aria-hidden="true" className={cn('flex shrink-0 items-center justify-center rounded-qb-md bg-qb-brand-soft text-qb-brand', className)}>
      <DynamicIcon name={icon} strokeWidth={1.5} className="size-5 qb-tablet:size-6" />
    </span>
  );
}

/** Category card of "Discover Categories" (185:6576, 536:32620, 621:27193). */
export function CategoryCard({ href, name, count, icon, className }: CategoryTileProps) {
  return (
    <Link href={href} className={cn(tileBase, 'h-[120px] flex-col p-4 qb-tablet:h-[148px] qb-tablet:rounded-qb-2xl', className)}>
      <IconBox icon={icon} className="size-9 qb-tablet:size-[43px]" />
      <span className="mt-auto min-w-0">
        <span className="block truncate text-qb-caption leading-none font-medium text-qb-ink qb-tablet:text-qb-body qb-desktop:text-qb-h5">{name}</span>{' '}
        <span className="mt-[11px] block text-qb-micro leading-none text-qb-ink-subtle qb-tablet:mt-3.5 qb-tablet:text-qb-caption qb-desktop:mt-4">
          {count}
        </span>
      </span>
    </Link>
  );
}

/** Compact sub-category row of the category page's "Categories" block (18:912, 623:28688). */
export function SubcategoryTile({ href, name, count, icon, className }: CategoryTileProps) {
  return (
    <Link
      href={href}
      className={cn(
        tileBase,
        'min-h-14 items-center gap-2.5 rounded-qb-xl p-2 qb-tablet:min-h-[97px] qb-tablet:gap-3 qb-tablet:rounded-qb-2xl qb-tablet:px-[25px]',
        className,
      )}
    >
      <IconBox icon={icon} className="size-10 rounded-qb-sm qb-tablet:size-[47px] qb-tablet:rounded-qb-md" />
      <span className="min-w-0">
        <span className="block truncate text-qb-micro leading-tight font-medium text-qb-ink-body qb-tablet:text-qb-body">{name}</span>{' '}
        <span className="mt-1 block text-[11px] leading-tight text-qb-ink-subtle qb-tablet:mt-1.5 qb-tablet:text-qb-caption">{count}</span>
      </span>
    </Link>
  );
}
