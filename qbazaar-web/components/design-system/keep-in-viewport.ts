/** Room kept between a moved card and the edge of the viewport. */
const EDGE_GAP_PX = 8;

/**
 * Ref callback of a dropdown card that hangs beside its field. A card that
 * would cross the left or right edge of the viewport (an Arabic list under a
 * field near the edge of a small tablet) moves sideways to stay inside. It
 * measures again when the card or the window changes size, for example when
 * the options arrive after the card opened.
 */
export function keepInViewport(card: HTMLElement | null) {
  if (!card) return;
  const fit = () => {
    card.style.translate = '';
    const { left, right } = card.getBoundingClientRect();
    const rightLimit = document.documentElement.clientWidth - EDGE_GAP_PX;
    const shift = left < EDGE_GAP_PX ? EDGE_GAP_PX - left : Math.min(0, rightLimit - right);
    if (shift !== 0) card.style.translate = `${shift}px`;
  };
  fit();
  // The observer reports before the next paint, after every card of the list has moved, so a sub-card
  // measures again once the card around it is in place.
  const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(fit);
  observer?.observe(card);
  window.addEventListener('resize', fit);
  return () => {
    observer?.disconnect();
    window.removeEventListener('resize', fit);
  };
}
