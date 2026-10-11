import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

/**
 * The reference's peach "Loading More Ads" button under a list: 15 px / 600
 * brand on #fff0ea, r12, 14 × 30 px padding and the buttons' 1.15 line
 * height (45 px tall). It has no hover change.
 */
export const loadMoreButton = cn(
  'inline-flex cursor-pointer items-center gap-2 rounded-qb-lg bg-qb-brand-soft px-[30px] py-3.5 font-qb text-qb-body-sm leading-[1.15] font-semibold text-qb-brand',
  focusRing,
);
