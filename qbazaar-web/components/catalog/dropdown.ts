import './catalog-tokens.css';

/** Fades the dropdown panels in and out with Base UI's transition attributes. */
const panelMotion =
  'origin-(--transform-origin) transition-opacity duration-150 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none';

/** Sort menu panel: a light grey card under the trigger, as wide as it (259:5246). */
export const sortPanel = `${panelMotion} min-w-(--anchor-width) rounded-qb-sm border border-qb-line bg-qb-hover py-3 font-qb shadow-qb-card outline-none`;

/**
 * Sort menu row: the chosen row light grey, a hovered one a shade lighter
 * (259:5246). Base UI moves the focus along the rows, so keyboard users also
 * get the focus outline.
 */
export const sortRow = [
  'flex h-[34px] cursor-pointer items-center px-4 text-qb-body leading-none font-medium tracking-[-0.4px] whitespace-nowrap text-(--color-qb-option-ink) select-none',
  'aria-selected:bg-(--color-qb-option-active) not-aria-selected:data-highlighted:bg-qb-fill-strong',
  'outline-none focus-visible:outline-solid focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-qb-brand-active',
].join(' ');

/** Searchable select panel: white, light border, a search row on top of the list (264:4818). */
export const selectPanel = `${panelMotion} w-(--anchor-width) overflow-hidden rounded-qb-lg border border-qb-line bg-qb-surface font-qb outline-none`;

/** Searchable select row (264:4818). */
export const selectRow = [
  'flex min-h-[30px] cursor-pointer items-center px-4 py-1 text-qb-micro leading-[22px] font-medium tracking-[-0.4px] text-(--color-qb-option-ink) outline-none select-none',
  'data-highlighted:bg-(--color-qb-option-active) aria-selected:bg-(--color-qb-option-active)',
].join(' ');

/**
 * The panel opens with its first row grey (264:4818) while no row is
 * highlighted or chosen; a hovered or arrowed-to row takes the grey over.
 */
export const selectFirstRowHint =
  '[&:not(:has([data-highlighted])):not(:has([aria-selected=true]))>[role=option]:first-child]:bg-(--color-qb-option-active)';

/** The thin grey scrollbar of the select list (264:4818). */
export const selectScrollbar =
  '[scrollbar-color:var(--color-qb-scrollbar)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-qb-lg [&::-webkit-scrollbar-thumb]:bg-(--color-qb-scrollbar)';
