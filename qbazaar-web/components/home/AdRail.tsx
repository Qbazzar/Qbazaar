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
    const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    list.scrollTo({ left: direction * page * list.clientWidth, behavior });
  };

  return (
    <div>
      <div className="relative">
        {/* The 4 px padding, taken back by the negative margin, leaves room for the cards' focus outline and hover lift. */}
        <ul
          ref={listRef}
          aria-label={label}
          onScroll={measure}
          className="-mx-1 -mt-1 flex snap-x snap-mandatory scroll-px-1 gap-5 overflow-x-auto px-1 pt-1 pb-1.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {Children.map(children, (card) => (
            <li className="w-[clamp(258px,23vw,300px)] shrink-0 snap-start">{card}</li>
          ))}
        </ul>
        <RailArrow side="start" label={t('ui.rail.previous')} inactive={position.atStart} onClick={() => scrollToPage(position.page - 1)} />
        <RailArrow side="end" label={t('ui.rail.next')} inactive={position.atEnd} onClick={() => scrollToPage(position.page + 1)} />
      </div>
      {position.pages > 1 ? <RailDots page={position.page} pages={position.pages} /> : null}
    </div>
  );
}

interface RailArrowProps {
  side: 'start' | 'end';
  label: string;
  /** At that end of the rail: hidden, unless it holds the focus, so keyboard users do not lose their place. */
  inactive: boolean;
  onClick: () => void;
}

function RailArrow({ side, label, inactive, onClick }: RailArrowProps) {
  return (
    <button
      type="button"
      aria-label={label}
      aria-disabled={inactive || undefined}
      onClick={inactive ? undefined : onClick}
      className={cn(
        'absolute top-[57px] flex size-[42px] cursor-pointer items-center justify-center rounded-full border border-qb-brand bg-qb-surface text-qb-brand shadow-qb-lift transition-colors hover:bg-qb-brand hover:text-qb-on-brand',
        'aria-disabled:cursor-default aria-disabled:opacity-40 aria-disabled:hover:bg-qb-surface aria-disabled:hover:text-qb-brand aria-disabled:not-focus:invisible',
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
