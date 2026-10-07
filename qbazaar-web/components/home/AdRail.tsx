'use client';

import { Children, useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { ArrowLeft, ArrowRight } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface AdRailProps {
  /** Accessible name of the list, usually the section title. */
  label: string;
  /** The cards; each becomes a list item. */
  children: ReactNode;
}

export interface RailPosition {
  page: number;
  pages: number;
  atStart: boolean;
  atEnd: boolean;
}

const UNMEASURED: RailPosition = { page: 0, pages: 1, atStart: true, atEnd: true };

/**
 * Where a horizontally scrolled list stands, in pages of its visible width.
 * scrollLeft is negative in RTL, so the offset is read as a distance. A list
 * that is not laid out (zero width) counts as a single page.
 */
export function railPosition(scrollLeft: number, scrollWidth: number, clientWidth: number): RailPosition {
  if (clientWidth <= 0) return UNMEASURED;
  const offset = Math.abs(scrollLeft);
  const max = Math.max(0, scrollWidth - clientWidth);
  const pages = Math.max(1, Math.ceil(scrollWidth / clientWidth));
  return {
    page: Math.min(pages - 1, Math.round(offset / clientWidth)),
    pages,
    atStart: offset <= 1,
    atEnd: offset >= max - 1,
  };
}

const samePosition = (a: RailPosition, b: RailPosition) =>
  a.page === b.page && a.pages === b.pages && a.atStart === b.atStart && a.atEnd === b.atEnd;

/**
 * Horizontal card slider of the home sections: native scroll with snap
 * (touch and trackpad work as is), arrows on the sides and page dots.
 */
export function AdRail({ label, children }: AdRailProps) {
  const listRef = useRef<HTMLUListElement>(null);
  const [position, setPosition] = useState<RailPosition>(UNMEASURED);

  const measure = useCallback(() => {
    const list = listRef.current;
    if (!list) return;
    const next = railPosition(list.scrollLeft, list.scrollWidth, list.clientWidth);
    // Scroll events fire every frame; only a changed position re-renders.
    setPosition((current) => (samePosition(current, next) ? current : next));
  }, []);

  const count = Children.count(children);
  useEffect(() => {
    const list = listRef.current;
    if (!list) return;
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(list);
    return () => observer.disconnect();
  }, [measure, count]);

  const scrollToPage = (page: number) => {
    const list = listRef.current;
    if (!list) return;
    const direction = getComputedStyle(list).direction === 'rtl' ? -1 : 1;
    list.scrollTo({ left: direction * page * list.clientWidth, behavior: 'smooth' });
  };

  return (
    <div>
      <div className="relative">
        <ul
          ref={listRef}
          aria-label={label}
          onScroll={measure}
          className="flex snap-x snap-mandatory gap-5 overflow-x-auto pb-1.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {Children.map(children, (card) => (
            <li className="w-[clamp(258px,23vw,300px)] shrink-0 snap-start">{card}</li>
          ))}
        </ul>
        {!position.atStart ? (
          <RailArrow side="start" label={t('ui.rail.previous')} onClick={() => scrollToPage(position.page - 1)} />
        ) : null}
        {!position.atEnd ? (
          <RailArrow side="end" label={t('ui.rail.next')} onClick={() => scrollToPage(position.page + 1)} />
        ) : null}
      </div>
      {position.pages > 1 ? <RailDots page={position.page} pages={position.pages} /> : null}
    </div>
  );
}

function RailArrow({ side, label, onClick }: { side: 'start' | 'end'; label: string; onClick: () => void }) {
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      className={cn(
        'absolute top-[57px] flex size-[42px] cursor-pointer items-center justify-center rounded-full border border-qb-brand bg-qb-surface text-qb-brand shadow-qb-lift transition-colors hover:bg-qb-brand hover:text-qb-on-brand',
        side === 'start' ? '-start-2' : '-end-2',
        focusRing,
      )}
    >
      <Icon icon={side === 'start' ? ArrowLeft : ArrowRight} flipInRtl className="size-[19px]" />
    </button>
  );
}

/** Page indicator only; the arrows and native scrolling do the navigation. */
function RailDots({ page, pages }: { page: number; pages: number }) {
  return (
    <div aria-hidden="true" className="mt-[18px] flex items-center justify-center gap-[7px]">
      {Array.from({ length: pages }, (_, index) => (
        <span
          key={index}
          className={cn('h-[9px] rounded-qb-pill transition-[width]', index === page ? 'w-10 bg-qb-brand' : 'w-[9px] bg-qb-brand-tint')}
        />
      ))}
    </div>
  );
}
