/**
 * Keyboard focus outline shared by every interactive design-system element.
 * Tailwind 4's `outline-none` also sets `--tw-outline-style: none`, which
 * `outline-2` reads, so the focus state names its own style. A bare
 * `outline-hidden` would draw a permanent outline in forced-colors mode.
 */
export const focusRing =
  'outline-none focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-qb-brand-active';
