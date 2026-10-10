import { cardVariants } from '@/components/design-system/Card';
import { cn } from '@/lib/utils';

/** The flat white r16 panel product.html builds the ad detail from (1px line, no shadow). */
export const detailCard = cn(cardVariants({ padding: 'none' }));

/** Padding of the main-column panels (title, description, specs): 24 px at every width. */
export const detailCardMain = 'p-6';

/** Padding of the sidebar panels (seller, ad facts). */
export const detailCardSide = 'p-[22px]';

/** "Description", "Technical Data"...: 22 px / 500 in #333 at every width (typo.css). */
export const detailCardTitle = 'text-qb-h4 font-medium tracking-normal text-qb-ink-title';
