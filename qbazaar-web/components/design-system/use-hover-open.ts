'use client';

import { useEffect, useRef, type PointerEvent } from 'react';

/**
 * Opens a header menu while a mouse rests on it, as the reference's
 * onmouseenter / onmouseleave do. Touch and pen taps are left to the click
 * handler. `closeDelay` lets the pointer cross a gap between the trigger and
 * the panel. `takeHoverOpen` tells a click whether the menu is open only
 * because of the hover (and hands it over to the click), so the click can keep
 * it open instead of toggling it shut.
 */
export function useHoverOpen(setOpen: (open: boolean) => void, closeDelay = 0) {
  const timer = useRef<number | undefined>(undefined);
  const hoverOpened = useRef(false);

  useEffect(() => () => window.clearTimeout(timer.current), []);

  const onPointerEnter = (event: PointerEvent) => {
    if (event.pointerType !== 'mouse') return;
    window.clearTimeout(timer.current);
    hoverOpened.current = true;
    setOpen(true);
  };

  const onPointerLeave = (event: PointerEvent) => {
    if (event.pointerType !== 'mouse') return;
    window.clearTimeout(timer.current);
    const close = () => {
      hoverOpened.current = false;
      setOpen(false);
    };
    if (closeDelay > 0) timer.current = window.setTimeout(close, closeDelay);
    else close();
  };

  const takeHoverOpen = () => {
    const opened = hoverOpened.current;
    hoverOpened.current = false;
    return opened;
  };

  return { takeHoverOpen, hoverHandlers: { onPointerEnter, onPointerLeave } };
}
