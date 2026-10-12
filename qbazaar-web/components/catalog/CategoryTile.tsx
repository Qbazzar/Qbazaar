import Link from 'next/link';

import { cardHover } from '@/components/design-system/card-hover';
import { focusRing } from '@/components/design-system/focus-ring';
import { CategoryIcon } from '@/components/design-system/icons/CategoryIcon';
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

/** The reference's flat `.qb-card`: no shadow at rest, it lifts 3 px with the hover shadow. */
const tileBase = cn('flex cursor-pointer border border-qb-line bg-qb-surface font-qb', cardHover, focusRing);

/** The peach icon square; empty without an icon. */
function IconBox({ icon, className }: { icon?: string | null; className?: string }) {
  return (
    <span aria-hidden="true" className={cn('flex size-11 shrink-0 items-center justify-center bg-qb-brand-soft text-qb-brand', className)}>
      {icon ? <CategoryIcon name={icon} className="size-6" /> : null}
    </span>
  );
}

/** Category card of all-categories.html: the same 22 px card at every width. */
export function CategoryCard({ href, name, count, icon, className }: CategoryTileProps) {
  return (
    <Link href={href} className={cn(tileBase, 'flex-col gap-4 rounded-qb-xl p-[22px]', className)}>
      <IconBox icon={icon} className="rounded-qb-lg" />
      <span className="min-w-0">
        <span className="mb-1.5 block truncate text-[17px] leading-normal font-medium text-qb-ink">{name}</span>{' '}
        <span className="block text-qb-caption text-qb-ink-subtle">{count}</span>
      </span>
    </Link>
  );
}

/**
 * Sub-category card of parent-category.html (`.qb-catgrid`): a `.qb-card.qb-btn`, so the
 * hover also dims it like the reference's buttons. Its peach square stays empty, as in the
 * reference.
 */
export function SubcategoryTile({ href, name, count, className }: Omit<CategoryTileProps, 'icon'>) {
  return (
    <Link
      href={href}
      className={cn(tileBase, 'items-center gap-3.5 rounded-[14px] p-4 leading-[1.15] hover:brightness-[.96]', className)}
    >
      <IconBox className="rounded-qb-md" />
      <span className="min-w-0">
        <span className="block truncate text-qb-body-sm leading-[1.15] font-medium text-qb-ink-title">{name}</span>{' '}
        <span className="mt-0.5 block text-qb-label leading-[1.15] text-qb-ink-subtle">{count}</span>
      </span>
    </Link>
  );
}
