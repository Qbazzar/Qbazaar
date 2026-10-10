import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { AdRail, railPosition, railStep } from './AdRail';

function sizeList(list: HTMLElement, { scrollWidth, clientWidth, scrollLeft = 0 }: Record<string, number>) {
  Object.defineProperty(list, 'scrollWidth', { configurable: true, value: scrollWidth });
  Object.defineProperty(list, 'clientWidth', { configurable: true, value: clientWidth });
  list.scrollLeft = scrollLeft;
}

function renderRail() {
  render(
    <AdRail label="Latest ads">
      {['One', 'Two', 'Three'].map((label) => (
        <a key={label} href={`/ads/${label}`}>
          {label}
        </a>
      ))}
    </AdRail>,
  );
  const list = screen.getByRole('list');
  list.scrollTo = vi.fn();
  list.scrollBy = vi.fn();
  return list;
}

describe('railPosition', () => {
  it('counts pages of the visible width and knows both ends', () => {
    expect(railPosition(0, 1000, 400)).toEqual({ page: 0, pages: 3, atStart: true, atEnd: false });
    expect(railPosition(600, 1000, 400)).toEqual({ page: 2, pages: 3, atStart: false, atEnd: true });
  });

  it('makes the last page current at the end, even short of a full page', () => {
    expect(railPosition(460, 1180, 720)).toEqual({ page: 1, pages: 2, atStart: false, atEnd: true });
    expect(railPosition(500, 1300, 400)).toMatchObject({ page: 1, atEnd: false });
  });

  it('reads a negative RTL scrollLeft as a distance', () => {
    expect(railPosition(-600, 1000, 400)).toEqual({ page: 2, pages: 3, atStart: false, atEnd: true });
  });

  it('treats a list that is not laid out yet as one page', () => {
    expect(railPosition(0, 1000, 0)).toEqual({ page: 0, pages: 1, atStart: true, atEnd: true });
  });
});

describe('railStep', () => {
  it('moves by 85% of the visible width, or one card when that is wider', () => {
    const list = document.createElement('ul');
    const card = document.createElement('li');
    list.append(card);
    Object.defineProperty(list, 'clientWidth', { configurable: true, value: 1360 });
    card.getBoundingClientRect = () => ({ width: 300 }) as DOMRect;
    expect(railStep(list)).toBe(1156);

    Object.defineProperty(list, 'clientWidth', { configurable: true, value: 300 });
    expect(railStep(list)).toBe(320);
  });
});

describe('AdRail', () => {
  beforeEach(() => setClientLocale('en'));

  it('renders a labelled list with one item per card', () => {
    renderRail();

    expect(screen.getByRole('list', { name: 'Latest ads' }).children).toHaveLength(3);
  });

  it('turns off the arrow of the end it reached and moves by most of the visible width', () => {
    const list = renderRail();
    sizeList(list, { scrollWidth: 1000, clientWidth: 400 });
    fireEvent.scroll(list);

    const previous = screen.getByRole('button', { name: 'Previous' });
    const next = screen.getByRole('button', { name: 'Next' });
    expect(previous).toHaveAttribute('aria-disabled', 'true');
    fireEvent.click(previous);
    expect(list.scrollBy).not.toHaveBeenCalled();
    fireEvent.click(next);
    expect(list.scrollBy).toHaveBeenCalledWith({ left: 340, behavior: 'smooth' });

    sizeList(list, { scrollWidth: 1000, clientWidth: 400, scrollLeft: 600 });
    fireEvent.scroll(list);
    expect(next).toHaveAttribute('aria-disabled', 'true');
    expect(previous).not.toHaveAttribute('aria-disabled');
  });

  it('keeps the focused arrow in place when the rail reaches that end', () => {
    const list = renderRail();
    sizeList(list, { scrollWidth: 1000, clientWidth: 400, scrollLeft: 400 });
    fireEvent.scroll(list);
    const next = screen.getByRole('button', { name: 'Next' });
    next.focus();

    sizeList(list, { scrollWidth: 1000, clientWidth: 400, scrollLeft: 600 });
    fireEvent.scroll(list);
    expect(next).toHaveFocus();
    expect(next).toHaveAttribute('aria-disabled', 'true');
  });

  it('jumps instead of gliding when the user asks for less motion', () => {
    const matchMedia = vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: true } as MediaQueryList);
    const list = renderRail();
    sizeList(list, { scrollWidth: 1000, clientWidth: 400 });
    fireEvent.scroll(list);

    fireEvent.click(screen.getByRole('button', { name: 'Next' }));
    expect(list.scrollBy).toHaveBeenCalledWith({ left: 340, behavior: 'auto' });
    matchMedia.mockRestore();
  });

  it('has a dot button per page that jumps to its page and marks the current one', () => {
    const list = renderRail();
    sizeList(list, { scrollWidth: 1000, clientWidth: 400, scrollLeft: 400 });
    fireEvent.scroll(list);

    const dots = screen.getAllByRole('button', { name: /^Go to slide/ });
    expect(dots).toHaveLength(3);
    expect(dots[1]).toHaveAttribute('aria-current', 'true');
    fireEvent.click(dots[0]);
    expect(list.scrollTo).toHaveBeenCalledWith({ left: 0, behavior: 'smooth' });
    fireEvent.click(dots[2]);
    expect(list.scrollTo).toHaveBeenCalledWith({ left: 800, behavior: 'smooth' });
  });

  it('scrolls with a mouse drag and swallows the click that ends it', () => {
    const list = renderRail();
    sizeList(list, { scrollWidth: 1000, clientWidth: 400, scrollLeft: 100 });
    const card = screen.getByRole('link', { name: 'One' });
    const opened = vi.fn((event: Event) => event.preventDefault());
    card.addEventListener('click', opened);

    fireEvent.pointerDown(list, { pointerType: 'mouse', button: 0, buttons: 1, clientX: 500, pointerId: 1 });
    expect(list).toHaveClass('cursor-grabbing');
    fireEvent.pointerMove(list, { pointerType: 'mouse', buttons: 1, clientX: 380, pointerId: 1 });
    expect(list.scrollLeft).toBe(220);
    fireEvent.pointerUp(list, { pointerType: 'mouse', pointerId: 1 });
    fireEvent.click(card);
    expect(opened).not.toHaveBeenCalled();
    expect(list).not.toHaveClass('cursor-grabbing');

    // A press without movement is a plain click.
    fireEvent.pointerDown(list, { pointerType: 'mouse', button: 0, buttons: 1, clientX: 300, pointerId: 2 });
    fireEvent.pointerMove(list, { pointerType: 'mouse', buttons: 1, clientX: 302, pointerId: 2 });
    fireEvent.pointerUp(list, { pointerType: 'mouse', pointerId: 2 });
    fireEvent.click(card);
    expect(opened).toHaveBeenCalledTimes(1);
  });

  it('leaves touch scrolling to the browser', () => {
    const list = renderRail();
    sizeList(list, { scrollWidth: 1000, clientWidth: 400, scrollLeft: 100 });

    fireEvent.pointerDown(list, { pointerType: 'touch', button: 0, buttons: 1, clientX: 500, pointerId: 3 });
    fireEvent.pointerMove(list, { pointerType: 'touch', buttons: 1, clientX: 300, pointerId: 3 });
    expect(list.scrollLeft).toBe(100);
  });

  it('does not break while the list has no width', () => {
    const list = renderRail();
    sizeList(list, { scrollWidth: 1000, clientWidth: 0 });
    fireEvent.scroll(list);

    expect(screen.getByRole('list', { name: 'Latest ads' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Next' })).toHaveAttribute('aria-disabled', 'true');
  });
});
