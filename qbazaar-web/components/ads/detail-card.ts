import { cardVariants } from '@/components/design-system/Card';
import { cn } from '@/lib/utils';

/** White r24 panel the ad detail page is built from. */
export const detailCard = cn(cardVariants({ large: true, elevated: true, padding: 'none' }));

/** Padding of the main-column panels (title, description, specs). */
export const detailCardMain = 'p-[17px] qb-tablet:p-5 qb-desktop:p-8';

/** Padding of the sidebar panels (seller, ad facts, location). */
export const detailCardSide = 'p-5 qb-tablet:p-6';

/** "Description", "Technical Data"... */
export const detailCardTitle =
  'text-qb-body leading-[1.25] font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-caption qb-desktop:text-qb-h4';
