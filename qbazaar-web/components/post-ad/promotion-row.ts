import { cn } from '@/lib/utils';

/** Outlines a row whose hidden input has the keyboard focus. */
export const focusWithinRow =
  'has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-qb-brand-active';

/**
 * A promotion row of add-ads.html, also used by the promote page: a 1 px
 * border, peach with an orange border when chosen, and 1.15 line heights, so
 * a row with one line of description is 81 px. Arabic keeps the normal line
 * height: its taller glyphs collide at 1.15 once a description wraps.
 */
export const promotionRow = {
  shell: (checked: boolean) =>
    cn(
      'group flex cursor-pointer items-center gap-4 rounded-qb-lg border px-5 py-[18px] transition-colors',
      focusWithinRow,
      checked ? 'border-qb-brand bg-(--color-qb-promo-active)' : 'border-qb-line bg-qb-surface',
    ),
  title: 'block text-qb-h5 leading-[1.15] font-medium text-qb-ink-title rtl:leading-normal',
  body: 'mt-1 block text-qb-caption leading-[1.15] text-qb-ink-subtle rtl:leading-normal',
  price: 'shrink-0 text-qb-body font-semibold whitespace-nowrap text-qb-ink',
} as const;
