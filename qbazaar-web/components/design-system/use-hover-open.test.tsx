import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useHoverOpen } from './use-hover-open';

const setOpen = vi.fn();

function Probe({ delay = 160 }: { delay?: number }) {
  const { hoverHandlers, takeHoverOpen } = useHoverOpen(setOpen, delay);
  return (
    <button type="button" data-testid="trigger" onClick={() => setOpen(takeHoverOpen())} {...hoverHandlers}>
      trigger
    </button>
  );
}

const trigger = () => screen.getByTestId('trigger');

describe('useHoverOpen', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    setOpen.mockClear();
  });
  afterEach(() => vi.useRealTimers());

  it('opens when the mouse enters and closes only after the delay', () => {
    render(<Probe />);
    fireEvent.pointerEnter(trigger(), { pointerType: 'mouse' });
    expect(setOpen).toHaveBeenLastCalledWith(true);

    fireEvent.pointerLeave(trigger(), { pointerType: 'mouse' });
    vi.advanceTimersByTime(159);
    expect(setOpen).toHaveBeenCalledTimes(1);
    vi.advanceTimersByTime(1);
    expect(setOpen).toHaveBeenLastCalledWith(false);
  });

  it('cancels the close when the mouse comes back in time', () => {
    render(<Probe />);
    fireEvent.pointerEnter(trigger(), { pointerType: 'mouse' });
    fireEvent.pointerLeave(trigger(), { pointerType: 'mouse' });
    vi.advanceTimersByTime(100);
    fireEvent.pointerEnter(trigger(), { pointerType: 'mouse' });
    vi.advanceTimersByTime(500);

    expect(setOpen).toHaveBeenCalledTimes(2);
    expect(setOpen).not.toHaveBeenCalledWith(false);
  });

  it('closes at once without a delay', () => {
    render(<Probe delay={0} />);
    fireEvent.pointerEnter(trigger(), { pointerType: 'mouse' });
    fireEvent.pointerLeave(trigger(), { pointerType: 'mouse' });

    expect(setOpen).toHaveBeenLastCalledWith(false);
  });

  it('tells a click that the hover opened the menu, once', () => {
    render(<Probe />);
    fireEvent.pointerEnter(trigger(), { pointerType: 'mouse' });
    fireEvent.click(trigger());
    expect(setOpen).toHaveBeenLastCalledWith(true);

    fireEvent.click(trigger());
    expect(setOpen).toHaveBeenLastCalledWith(false);
  });

  it('ignores touch and pen pointers', () => {
    render(<Probe />);
    for (const pointerType of ['touch', 'pen']) {
      fireEvent.pointerEnter(trigger(), { pointerType });
      fireEvent.pointerLeave(trigger(), { pointerType });
    }
    vi.advanceTimersByTime(1000);

    expect(setOpen).not.toHaveBeenCalled();
  });

  it('drops a pending close when it unmounts', () => {
    const { unmount } = render(<Probe />);
    fireEvent.pointerEnter(trigger(), { pointerType: 'mouse' });
    fireEvent.pointerLeave(trigger(), { pointerType: 'mouse' });
    unmount();
    vi.advanceTimersByTime(500);

    expect(setOpen).toHaveBeenCalledTimes(1);
  });
});
