/**
 * The white dropdown card of the reference's hero selects (selects.js,
 * `.qb-ddpanel`), shared by every list that opens under a field.
 */
export const dropdownPanel = {
  /** The card: 14 px under its field, at least 190 px or the field's width. */
  card: 'z-50 min-w-[max(190px,100%)] rounded-qb-xl bg-qb-surface py-1.5 text-start font-qb shadow-qb-dropdown',
  /** Places the card under the field's start edge; the field must be positioned. */
  placement: 'absolute start-0 top-[calc(100%+14px)]',
  /** The "All Categories" row heading the list. */
  heading:
    'cursor-pointer border-b border-qb-menu-divider px-5 pt-3.5 pb-2.5 text-qb-body leading-[1.3] font-semibold text-qb-menu-heading',
  row: 'flex cursor-pointer items-center justify-between gap-3 px-5 py-[11px] text-qb-caption leading-[1.3] whitespace-nowrap text-qb-menu-item',
  /** The highlighted row: under the pointer, reached with the arrow keys or holding the open sub-list. */
  activeRow: 'bg-qb-menu-active text-qb-menu-item-active',
  /** The › of a row with a sub-list, turned to point left in Arabic. */
  caret: 'inline-block text-[15px] leading-none text-qb-menu-caret rtl:-scale-x-100',
  /** The second card, 14 px beside the first; placed inside its positioned row, which sets the top. */
  subCard: 'absolute start-[calc(100%+14px)] min-w-[260px]',
} as const;
