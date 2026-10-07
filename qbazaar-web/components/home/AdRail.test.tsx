import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { AdRail, railPosition } from './AdRail';

function sizeList(list: HTMLElement, { scrollWidth, clientWidth, scrollLeft = 0 }: Record<string, number>) {
  Object.defineProperty(list, 'scrollWidth', { configurable: true, value: scrollWidth });
  Object.defineProperty(list, 'clientWidth', { configurable: true, value: clientWidth });
  list.scrollLeft = scrollLeft;
}

const cards = ['One', 'Two', 'Three'].map((label) => <span key={label}>{label}</span>);

describe('railPosition', () => {
  it('counts pages of the visible width and knows both ends', () => {
    expect(railPosition(0, 1000, 400)).toEqual({ page: 0, pages: 3, atStart: true, atEnd: false });
    expect(railPosition(600, 1000, 400)).toEqual({ page: 2, pages: 3, atStart: false, atEnd: true });
  });

  it('reads a negative RTL scrollLeft as a distance', () => {
    expect(railPosition(-600, 1000, 400)).toEqual({ page: 2, pages: 3, atStart: false, atEnd: true });
  });

  it('treats a list that is not laid out yet as one page', () => {
    expect(railPosition(0, 1000, 0)).toEqual({ page: 0, pages: 1, atStart: true, atEnd: true });
  });
});

describe('AdRail', () => {
  beforeEach(() => setClientLocale('en'));

  it('renders a labelled list with one item per card', () => {
    render(<AdRail label="Latest ads">{cards}</AdRail>);

    expect(screen.getByRole('list', { name: 'Latest ads' }).children).toHaveLength(3);
  });

  it('shows only the arrows that lead somewhere and scrolls a page at a time', () => {
    render(<AdRail label="Latest ads">{cards}</AdRail>);
    const list = screen.getByRole('list');
    list.scrollTo = vi.fn();
    sizeList(list, { scrollWidth: 1000, clientWidth: 400 });
    fireEvent.scroll(list);

    expect(screen.queryByRole('button', { name: 'Previous' })).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Next' }));
    expect(list.scrollTo).toHaveBeenCalledWith({ left: 400, behavior: 'smooth' });

    sizeList(list, { scrollWidth: 1000, clientWidth: 400, scrollLeft: 600 });
    fireEvent.scroll(list);
    expect(screen.queryByRole('button', { name: 'Next' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Previous' })).toBeInTheDocument();
  });

  it('does not break while the list has no width', () => {
    render(<AdRail label="Latest ads">{cards}</AdRail>);
    const list = screen.getByRole('list');
    sizeList(list, { scrollWidth: 1000, clientWidth: 0 });
    fireEvent.scroll(list);

    expect(screen.getByRole('list', { name: 'Latest ads' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Next' })).toBeNull();
  });
});
