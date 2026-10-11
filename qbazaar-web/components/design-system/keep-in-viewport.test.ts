import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { keepInViewport } from './keep-in-viewport';

const VIEWPORT_WIDTH = 601;

/** A card whose box spans `left` to `right` before any shift. */
function cardAt(left: number, right: number) {
  const card = document.createElement('div');
  vi.spyOn(card, 'getBoundingClientRect').mockImplementation(() => {
    const shift = Number.parseFloat(card.style.translate) || 0;
    return { left: left + shift, right: right + shift } as DOMRect;
  });
  return card;
}

describe('keepInViewport', () => {
  beforeEach(() => {
    vi.spyOn(document.documentElement, 'clientWidth', 'get').mockReturnValue(VIEWPORT_WIDTH);
  });

  afterEach(() => vi.restoreAllMocks());

  it('leaves a card inside the viewport where it is', () => {
    const card = cardAt(386, 576);
    keepInViewport(card);

    expect(card.style.translate).toBe('');
  });

  it('moves a card that crosses the left edge 8 px inside it', () => {
    const card = cardAt(-14, 176);
    keepInViewport(card);

    expect(card.style.translate).toBe('22px');
  });

  it('moves a card that crosses the right edge 8 px inside it', () => {
    const card = cardAt(438, 698);
    keepInViewport(card);

    expect(card.style.translate).toBe('-105px');
  });

  it('measures again when the window changes size, and stops once the card is gone', () => {
    const card = cardAt(-14, 176);
    const cleanup = keepInViewport(card);
    card.style.translate = '';

    window.dispatchEvent(new Event('resize'));
    expect(card.style.translate).toBe('22px');

    cleanup?.();
    card.style.translate = '';
    window.dispatchEvent(new Event('resize'));
    expect(card.style.translate).toBe('');
  });
});
