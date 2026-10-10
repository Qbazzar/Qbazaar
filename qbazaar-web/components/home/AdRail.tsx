'use client';

import {
  Children,
  useCallback,
  useEffect,
  useRef,
  useState,
  type DragEvent,
  type MouseEvent,
  type PointerEvent,
  type ReactNode,
  type RefObject,
} from 'react';
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
/** Pixels of slack at either end, as in the reference's slider.js. */
const END_SLACK = 2;
/** A mouse that moves less than this between press and release clicks instead of dragging. */
const DRAG_THRESHOLD = 4;
/** The list's side padding (px-1), which never makes a page of its own. */
const LIST_SIDE_PADDING = 4;

/**
 * Where a horizontally scrolled list stands, in pages of its visible width.
 * scrollLeft is negative in RTL, so the offset is read as a distance. The
 * last page is the current one once the end is reached. A list that is not
 * laid out (zero width) counts as a single page.
 */
export function railPosition(scrollLeft: number, scrollWidth: number, clientWidth: number): RailPosition {
  if (clientWidth <= 0) return UNMEASURED;
  const offset = Math.abs(scrollLeft);
  const max = Math.max(0, scrollWidth - clientWidth);
  const pages = Math.max(1, Math.ceil((scrollWidth - LIST_SIDE_PADDING) / clientWidth));
  const atEnd = offset >= max - END_SLACK;
  return {
    page: atEnd ? pages - 1 : Math.min(pages - 1, Math.round(offset / clientWidth)),
    pages,
    atStart: offset <= END_SLACK,
    atEnd,
  };
}

/** The arrows' step: one card and its gap, or 85% of the visible width when that is more. */
export function railStep(list: HTMLElement): number {
  const card = list.firstElementChild;
  const gap = parseFloat(getComputedStyle(list).columnGap) || 20;
  const cardStep = card ? card.getBoundingClientRect().width + gap : 300;
  return Math.max(cardStep, list.clientWidth * 0.85);
}

const samePosition = (a: RailPosition, b: RailPosition) =>
  a.page === b.page && a.pages === b.pages && a.atStart === b.atStart && a.atEnd === b.atEnd;

/** -1 when the list scrolls right to left, so offsets can be written for the left-to-right case. */
const directionOf = (list: HTMLElement) => (getComputedStyle(list).direction === 'rtl' ? -1 : 1);

const scrollBehavior = (): ScrollBehavior =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';

/**
 * Lets a mouse drag the list sideways (touch and trackpads scroll it
 * natively). The click that ends a drag is swallowed, so no card opens.
 */
function useDragScroll(listRef: RefObject<HTMLUListElement | null>) {
  const drag = useRef<{ x: number; scrollLeft: number; pointerId: number; moved: boolean } | null>(null);
  const swallowClick = useRef(false);
  const [pressed, setPressed] = useState(false);

  const end = () => {
    if (drag.current?.moved) swallowClick.current = true;
    drag.current = null;
    setPressed(false);
  };

  return {
    pressed,
    handlers: {
      onPointerDown: (event: PointerEvent<HTMLUListElement>) => {
        const list = listRef.current;
        if (!list || event.pointerType !== 'mouse' || event.button !== 0) return;
        drag.current = { x: event.clientX, scrollLeft: list.scrollLeft, pointerId: event.pointerId, moved: false };
        swallowClick.current = false;
        setPressed(true);
      },
      onPointerMove: (event: PointerEvent<HTMLUListElement>) => {
        const list = listRef.current;
        const current = drag.current;
        if (!list || !current) return;
        // The button came up outside the list.
        if ((event.buttons & 1) === 0) return end();
        const dx = event.clientX - current.x;
        if (!current.moved) {
          if (Math.abs(dx) <= DRAG_THRESHOLD) return;
          current.moved = true;
          list.setPointerCapture?.(current.pointerId);
        }
        list.scrollLeft = current.scrollLeft - dx;
      },
      onPointerUp: end,
      onPointerCancel: end,
      onClickCapture: (event: MouseEvent) => {
        if (!swallowClick.current) return;
        swallowClick.current = false;
        event.preventDefault();
        event.stopPropagation();
      },
      // A native drag of a card's link or photo would take over the pointer.
      onDragStart: (event: DragEvent) => event.preventDefault(),
    },
  };
}

/**
 * Horizontal card slider of the home sections (slider.js of the reference):
 * free scrolling (touch, trackpad and mouse drag), arrows that move by most
 * of the visible width, and page dots that jump to their page.
 */
export function AdRail({ label, children }: AdRailProps) {
  const listRef = useRef<HTMLUListElement>(null);
  const [position, setPosition] = useState<RailPosition>(UNMEASURED);
  const { pressed, handlers } = useDragScroll(listRef);

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

  const scrollByStep = (steps: number) => {
    const list = listRef.current;
    if (!list) return;
    list.scrollBy({ left: directionOf(list) * steps * railStep(list), behavior: scrollBehavior() });
  };

  const scrollToPage = (page: number) => {
    const list = listRef.current;
    if (!list) return;
    list.scrollTo({ left: directionOf(list) * page * list.clientWidth, behavior: scrollBehavior() });
  };

  return (
    <div>
      <div className="relative">
        {/* The 4 px padding, taken back by the negative margin, leaves room for the cards' focus outline and hover lift. */}
        <ul
          ref={listRef}
          aria-label={label}
          onScroll={measure}
          {...handlers}
          className={cn(
            '-mx-1 -mt-1 flex cursor-grab gap-5 overflow-x-auto px-1 pt-1 pb-1.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
            pressed && 'cursor-grabbing select-none [&_*]:cursor-grabbing',
          )}
        >
          {/* Every card fills its item, so the cards of a rail end on one line. */}
          {Children.map(children, (card) => (
            <li className="w-[clamp(258px,23vw,300px)] shrink-0 *:h-full">{card}</li>
          ))}
        </ul>
        <RailArrow side="start" label={t('ui.rail.previous')} inactive={position.atStart} onClick={() => scrollByStep(-1)} />
        <RailArrow side="end" label={t('ui.rail.next')} inactive={position.atEnd} onClick={() => scrollByStep(1)} />
      </div>
      {position.pages > 1 ? <RailDots page={position.page} pages={position.pages} onSelect={scrollToPage} /> : null}
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

/** One dot per page of the visible width; the current page's dot is the long one. */
function RailDots({ page, pages, onSelect }: { page: number; pages: number; onSelect: (page: number) => void }) {
  return (
    <div className="mt-[18px] flex items-center justify-center gap-[7px]">
      {Array.from({ length: pages }, (_, index) => (
        <button
          key={index}
          type="button"
          aria-label={t('ui.rail.go_to', { n: index + 1 })}
          aria-current={index === page || undefined}
          onClick={() => onSelect(index)}
          className={cn(
            'h-[9px] cursor-pointer rounded-qb-pill transition-[width,background-color] duration-200 motion-reduce:transition-none',
            index === page ? 'w-10 bg-qb-brand' : 'w-[9px] bg-qb-brand-tint',
            focusRing,
          )}
        />
      ))}
    </div>
  );
}
