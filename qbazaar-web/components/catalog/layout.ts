import './catalog-tokens.css';

/** Pulls a horizontal scroller out to the screen edge on phones and tablets. */
export const catalogBleed = '-mx-qb-gutter px-qb-gutter scroll-px-qb-gutter';

/** The listing pages pad their frame by the fluid gutter above and below (category.html, parent-category.html). */
export const catalogPageTop = 'py-[clamp(20px,4vw,40px)]';

/**
 * The base layer sets h1–h6 to its own weight and tracking; this keeps the
 * headings of the listing cards, SectionHeader, EmptyState and the dialogs on the
 * `font-qb` stack explicitly.
 */
export const headingFont = '[&_:is(h1,h2,h3,h4,h5,h6)]:font-qb';

/**
 * The white `.qb-card.qb-btn` pills of the listing pages ("Add to Favorite", "Save Search"):
 * 12 px corners and the light 30 px shadow; under the pointer they lift 3 px and dim
 * like every `.qb-btn`, the shadow stays.
 */
export const toolbarPill = [
  'inline-flex items-center rounded-qb-lg border border-qb-line bg-qb-surface text-qb-black shadow-(--shadow-qb-pill)',
  'qb-motion cursor-pointer hover:brightness-[.96] hover:transform-[translateY(-3px)] motion-reduce:transition-none motion-reduce:hover:transform-none',
].join(' ');
