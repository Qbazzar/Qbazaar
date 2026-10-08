/**
 * Keyboard focus outline shared by every interactive design-system element.
 * Tailwind 4's `outline-hidden` also sets `--tw-outline-style: none`, which
 * `outline-2` reads, so the focus state names its own style. `outline-hidden`
 * rather than `outline-none` keeps an outline in forced-colors mode.
 */
export const focusRing =
  'outline-hidden focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-qb-brand-active';
