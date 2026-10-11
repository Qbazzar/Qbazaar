/** Pulls a horizontal scroller out to the screen edge on phones and tablets. */
export const catalogBleed = '-mx-qb-gutter px-qb-gutter scroll-px-qb-gutter';

/** Vertical rhythm of the page top, measured on 185:6576, 536:32620 and 621:27193. */
export const catalogPageTop = 'pt-10 pb-16 qb-tablet:pt-[62px] qb-desktop:pt-[65px] qb-desktop:pb-24';

/**
 * The base layer sets h1–h6 to its own weight and tracking; this keeps the
 * headings of the listing cards, SectionHeader, EmptyState and the dialogs on the
 * `font-qb` stack explicitly.
 */
export const headingFont = '[&_:is(h1,h2,h3,h4,h5,h6)]:font-qb';

/** Raised white pill used by the toolbar controls (sort, filter, save search). */
export const toolbarPill =
  'inline-flex items-center rounded-qb-md border border-qb-line bg-qb-surface text-qb-ink shadow-qb-card';

/** The trail's links turn orange under the pointer, as the reference's `.qb-nav` crumbs do. */
export const breadcrumbHover = '[&_a]:transition-colors [&_a]:duration-200 [&_a:hover]:text-qb-brand motion-reduce:[&_a]:transition-none';
