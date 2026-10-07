'use client';

import { createContext, useCallback, useContext, useEffect, useRef, type ReactNode, type RefObject } from 'react';

const ResultsFocusContext = createContext<{ current: boolean } | null>(null);

/**
 * Lets a filter action hand keyboard focus to the results it produces. The
 * request is kept above the page's views because the result can be another
 * tree: the category overview turns into the listing, results into "Search
 * Not Found".
 */
export function ResultsFocusProvider({ children }: { children: ReactNode }) {
  const pending = useRef(false);
  return <ResultsFocusContext.Provider value={pending}>{children}</ResultsFocusContext.Provider>;
}

/** Call before applying filters whose control will not survive the change. */
export function useRequestResultsFocus(): () => void {
  const pending = useContext(ResultsFocusContext);
  return useCallback(() => {
    if (pending) pending.current = true;
  }, [pending]);
}

interface FocusTargetOptions {
  /** False while the results load: the request waits for what the filters produce. */
  ready?: boolean;
  /**
   * Take the focus only once the control that asked for it is gone (the focus
   * fell back to the page body), so a form that stays on screen keeps it.
   */
  onlyIfFocusLost?: boolean;
}

function focusIsLost(): boolean {
  const active = document.activeElement;
  return active === null || active === document.body;
}

/** Ref for an element with `tabIndex={-1}` that takes a pending focus request on a commit. */
export function useResultsFocusTarget<T extends HTMLElement>({ ready = true, onlyIfFocusLost = false }: FocusTargetOptions = {}): RefObject<T | null> {
  const pending = useContext(ResultsFocusContext);
  const ref = useRef<T>(null);

  useEffect(() => {
    const target = ref.current;
    if (!pending?.current || !target || !ready) return;
    if (onlyIfFocusLost && !focusIsLost()) return;
    pending.current = false;
    target.focus();
  });

  return ref;
}
