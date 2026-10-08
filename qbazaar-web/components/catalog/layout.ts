/** Pulls a horizontal scroller out to the screen edge on phones and tablets. */
export const catalogBleed = '-mx-qb-gutter px-qb-gutter scroll-px-qb-gutter';

/** Vertical rhythm of the page top, measured on 185:6576, 536:32620 and 621:27193. */
export const catalogPageTop = 'pt-10 pb-16 qb-tablet:pt-[62px] qb-desktop:pt-[65px] qb-desktop:pb-24';

/**
 * The base layer gives h1–h6 the old fonts (DM Sans, Cairo), which beats the
 * inherited `font-qb` on the headings of AdCard, SectionHeader, EmptyState and
 * the dialogs; this puts them back on the design font.
 */
export const headingFont = '[&_:is(h1,h2,h3,h4,h5,h6)]:font-qb';

/** Raised white pill used by the toolbar controls (sort, filter, save search). */
export const toolbarPill =
  'inline-flex items-center rounded-qb-md border border-qb-line bg-qb-surface text-qb-ink shadow-qb-card';
