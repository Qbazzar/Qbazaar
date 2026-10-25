'use client';

import { useEffect, useRef, useState, type RefObject } from 'react';

/**
 * True from the moment the element gets within `margin` of the viewport, and
 * for good after that. Where `IntersectionObserver` is missing the element
 * counts as visible at once, so nothing waits for an observer that never fires.
 */
export function useOnceVisible<T extends Element>(margin = '200px'): [RefObject<T | null>, boolean] {
  const ref = useRef<T | null>(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const element = ref.current;
    if (visible || !element) return;
    if (typeof IntersectionObserver === 'undefined') {
      setVisible(true);
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) setVisible(true);
      },
      { rootMargin: margin },
    );
    observer.observe(element);
    return () => observer.disconnect();
  }, [visible, margin]);

  return [ref, visible];
}
