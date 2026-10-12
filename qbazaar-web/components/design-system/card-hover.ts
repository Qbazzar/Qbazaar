/**
 * The reference's `.qb-card` hover: the card lifts 3 px with a transform and
 * its shadow deepens, on the polish transition (polish.css:16-20 overrides the
 * .18 s one of styles.css). Reduced motion keeps the shadow only.
 */
export const cardHover =
  'qb-motion hover:shadow-qb-hover hover:transform-[translateY(-3px)] motion-reduce:transition-none motion-reduce:hover:transform-none';
