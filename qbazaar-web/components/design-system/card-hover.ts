/**
 * The reference's `.qb-card` hover: the card lifts 3 px with a transform
 * (0.18 s ease) and its shadow deepens. Reduced motion keeps the shadow only.
 */
export const cardHover =
  'transition-[box-shadow,transform] duration-[180ms] ease-[ease] hover:shadow-qb-hover hover:transform-[translateY(-3px)] motion-reduce:transition-none motion-reduce:hover:transform-none';
