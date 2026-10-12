'use client';

import './field-select.css';

import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown } from 'lucide-react';

import { cn } from '@/lib/utils';

import {
  buildEntries,
  isPrintable,
  matchTyped,
  measure,
  resolveKey,
  type Entry,
  type FieldSelectOption,
  type Placement,
  type TypedText,
} from './field-select-model';

export type { FieldSelectOption } from './field-select-model';

export interface FieldSelectProps {
  id?: string;
  /** Names the open list for assistive tech; label the field itself with a <label htmlFor> or aria-labelledby. */
  label: string;
  value: string;
  options: readonly FieldSelectOption[];
  onChange: (value: string) => void;
  /** Shown in the placeholder grey while no option, or the option with the empty value, is chosen. */
  placeholder?: string;
  /** Names the row that chooses a parent from inside its own list, e.g. "Doha (all areas)". */
  parentLabel?: (option: FieldSelectOption) => string;
  disabled?: boolean;
  /** Classes of the field: its size and text. */
  className?: string;
  'aria-labelledby'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
  'aria-required'?: boolean;
}

/** The box of the design-system Input and Select (`controlBase` in Input.tsx). */
const fieldBox = [
  'relative flex h-[52px] w-full cursor-pointer items-center rounded-qb-md border border-qb-field-border bg-qb-surface ps-4 pe-11',
  'text-start font-qb text-qb-body text-qb-ink outline-none',
  'focus-visible:border-qb-brand focus-visible:ring-2 focus-visible:ring-qb-brand/20',
  'aria-invalid:border-qb-danger aria-invalid:focus-visible:ring-qb-danger/20',
  'disabled:cursor-not-allowed disabled:border-qb-line disabled:bg-qb-fill disabled:text-qb-ink-subtle',
].join(' ');

const panel = {
  card: 'rounded-qb-xl bg-qb-surface py-1.5 shadow-(--shadow-qb-dropdown)',
  /** `.qb-ddhead`: heads the list of one parent where the second card has no room beside the first. */
  heading:
    'flex cursor-pointer items-center gap-2 border-b border-(--color-qb-menu-divider) px-5 pt-3.5 pb-2.5 text-qb-body leading-[1.3] font-semibold text-(--color-qb-menu-heading)',
  row: 'flex cursor-pointer items-center justify-between gap-3 px-5 py-[11px] text-qb-caption leading-[1.3] whitespace-nowrap text-(--color-qb-menu-item)',
  activeRow: 'bg-(--color-qb-menu-active) text-(--color-qb-menu-item-active)',
  /** A mirrored glyph, so it points the other way in Arabic. */
  caret: 'text-[15px] leading-none text-(--color-qb-menu-caret)',
} as const;

/** A moved card keeps the side the second card was laid out on, so a row open at the time does not jump. */
function withSameSide(current: Placement | null, next: Placement): Placement {
  return current ? { ...next, beside: current.beside } : next;
}

/**
 * A form field that opens the reference's designed dropdown card (selects.js)
 * instead of the platform list: the card is portaled under the field, the
 * chevron turns while it is open, and a row with children opens a second card
 * beside it, or in place of the first where there is no room. A select-only
 * combobox: focus stays on the field, the arrow keys move the highlighted row
 * (towards a second card and back too), letters jump to a row, Enter or Space
 * chooses, Escape, Tab, a blur or a press outside closes. The card follows the
 * field on scroll and resize. Arabic mirrors the cards and the arrow keys.
 * A flat list is a listbox; only the two-level city cascade is a tree.
 */
export function FieldSelect({
  id,
  label,
  value,
  options,
  onChange,
  placeholder,
  parentLabel,
  disabled,
  className,
  ...aria
}: FieldSelectProps) {
  const baseId = useId();
  const listId = `${baseId}-list`;
  const fieldRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const pointerType = useRef('mouse');
  const spaceChose = useRef(false);
  const typed = useRef<TypedText>({ text: '', at: 0 });
  const [open, setOpen] = useState(false);
  const [placement, setPlacement] = useState<Placement | null>(null);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [drilledId, setDrilledId] = useState<string | null>(null);
  const [picked, setPicked] = useState(false);

  const { top, byId, byValue } = useMemo(() => buildEntries(baseId, options, parentLabel), [baseId, options, parentLabel]);
  const nested = top.some((entry) => entry.children.length > 0);
  const chosen = byValue.get(value);
  const showsPlaceholder = !chosen || (placeholder !== undefined && chosen.value === '');
  const active = activeId === null ? undefined : byId.get(activeId);
  const drilled = drilledId === null ? undefined : byId.get(drilledId);
  const beside = placement?.beside ?? false;
  const shownRows = drilled ? drilled.children : top;
  const level = active?.parent ? active.parent.children : shownRows;
  const expanded = beside ? (active?.parent ?? (active?.children.length ? active : undefined)) : undefined;

  const close = () => {
    setOpen(false);
    setDrilledId(null);
  };

  useEffect(() => {
    if (!open) return;
    const dismiss = () => {
      setOpen(false);
      setDrilledId(null);
    };
    const closeOnOutsidePress = (event: globalThis.PointerEvent) => {
      const target = event.target as Node;
      if (!fieldRef.current?.contains(target) && !panelRef.current?.contains(target)) dismiss();
    };
    // A new layout width closes the card (phones also resize as their toolbars slide while scrolling);
    // otherwise it follows its field, which moves when the page or a scrolling parent such as a dialog scrolls.
    const openWidth = window.innerWidth;
    let frame = 0;
    const follow = () => {
      if (window.innerWidth !== openWidth) return dismiss();
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        if (fieldRef.current) setPlacement((current) => withSameSide(current, measure(fieldRef.current as HTMLElement)));
      });
    };
    document.addEventListener('pointerdown', closeOnOutsidePress);
    window.addEventListener('resize', follow);
    window.addEventListener('scroll', follow, true);
    return () => {
      cancelAnimationFrame(frame);
      document.removeEventListener('pointerdown', closeOnOutsidePress);
      window.removeEventListener('resize', follow);
      window.removeEventListener('scroll', follow, true);
    };
  }, [open]);

  useEffect(() => {
    if (open && activeId) document.getElementById(activeId)?.scrollIntoView?.({ block: 'nearest' });
  }, [open, activeId]);

  const openPanel = (start: Entry | undefined = chosen ?? top[0]) => {
    if (!fieldRef.current) return;
    const next = measure(fieldRef.current);
    setPlacement(next);
    setDrilledId(!next.beside && start?.parent ? start.parent.id : null);
    setActiveId(start?.id ?? null);
    setOpen(true);
  };

  const choose = (entry: Entry) => {
    onChange(entry.value);
    setPicked(true);
    close();
  };

  /** Into a parent's list: its second card, or its rows in place of the first card. */
  const enter = (entry: Entry | undefined) => {
    const first = entry?.children[0];
    if (!entry || !first) return;
    if (!beside) setDrilledId(entry.id);
    setActiveId(first.id);
  };

  const leave = (entry: Entry | undefined) => {
    if (!entry?.parent) return;
    setDrilledId(null);
    setActiveId(entry.parent.id);
  };

  const jumpTo = (key: string, rows: Entry[], from: Entry | undefined) => {
    const { entry, next } = matchTyped(key, rows, from, typed.current, Date.now());
    typed.current = next;
    return entry;
  };

  const onClosedKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      openPanel();
    } else if (isPrintable(event)) {
      event.preventDefault();
      openPanel(jumpTo(event.key, top, chosen) ?? chosen ?? top[0]);
    }
  };

  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (disabled) return;
    if (!open) return onClosedKeyDown(event);
    if (event.key === 'Tab') return close();
    const rtl = getComputedStyle(event.currentTarget).direction === 'rtl';
    const action = resolveKey(event.key, level, active, rtl);
    if (!action) {
      if (isPrintable(event)) {
        event.preventDefault();
        highlight(jumpTo(event.key, level, active));
      }
      return;
    }
    event.preventDefault();
    if (action.type === 'close') event.stopPropagation();
    runKeyAction(action);
  };

  const highlight = (entry: Entry | undefined) => entry && setActiveId(entry.id);

  const runKeyAction = (action: NonNullable<ReturnType<typeof resolveKey>>) => {
    if (action.type === 'highlight') highlight(action.entry);
    else if (action.type === 'enter') enter(active);
    else if (action.type === 'leave') leave(active);
    else if (action.type === 'close') close();
    else {
      spaceChose.current = action.viaSpace;
      if (active) choose(active);
    }
  };

  // A button clicks on the Space key's release, which would reopen the list that key just closed.
  const onKeyUp = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (event.key !== ' ' || !spaceChose.current) return;
    spaceChose.current = false;
    event.preventDefault();
  };

  const rowHandlers = (entry: Entry) => ({
    onPointerDown: (event: PointerEvent) => {
      pointerType.current = event.pointerType;
    },
    onPointerEnter: (event: PointerEvent) => {
      if (event.pointerType === 'mouse') setActiveId(entry.id);
    },
    onClick: () => {
      // A touch has no hover to show the children first, so it opens them instead of choosing the parent.
      if (entry.children.length > 0 && (!beside || pointerType.current !== 'mouse')) enter(entry);
      else choose(entry);
    },
  });

  const isLit = (entry: Entry) => entry === active || entry === chosen || entry === expanded;
  const itemRole = nested ? 'treeitem' : 'option';
  /** A tree item says where it sits; the items of a listbox are counted by the browser. */
  const position = (index: number, siblings: Entry[], depth: number) =>
    nested ? { 'aria-level': depth, 'aria-setsize': siblings.length, 'aria-posinset': index + 1 } : {};

  const renderOption = (entry: Entry, index: number, siblings: Entry[], depth: number) => (
    <li
      key={entry.id}
      id={entry.id}
      role={itemRole}
      aria-selected={entry === chosen}
      {...position(index, siblings, depth)}
      className={cn(panel.row, isLit(entry) && panel.activeRow)}
      {...rowHandlers(entry)}
    >
      <span className="min-w-0 truncate">{entry.label}</span>
    </li>
  );

  const renderRow = (entry: Entry, index: number, siblings: Entry[]) => {
    if (entry.children.length === 0) return renderOption(entry, index, siblings, 1);
    const showsChildren = entry === expanded;
    return (
      <li
        key={entry.id}
        id={entry.id}
        role={itemRole}
        aria-selected={entry === chosen}
        aria-expanded={showsChildren}
        aria-label={entry.label}
        {...position(index, siblings, 1)}
        className="relative"
      >
        <div className={cn(panel.row, isLit(entry) && panel.activeRow)} {...rowHandlers(entry)}>
          <span className="min-w-0 truncate">{entry.label}</span>
          <span aria-hidden="true" className={panel.caret}>
            ›
          </span>
        </div>
        {showsChildren ? (
          <ul role="group" className={cn(panel.card, 'absolute start-[calc(100%+14px)] top-0.5 min-w-[260px]')}>
            {entry.children.map((child, childIndex, children) => renderOption(child, childIndex, children, 2))}
          </ul>
        ) : null}
      </li>
    );
  };

  return (
    <>
      <button
        ref={fieldRef}
        id={id}
        type="button"
        role="combobox"
        aria-haspopup={nested ? 'tree' : 'listbox'}
        aria-expanded={open}
        aria-controls={open ? listId : undefined}
        aria-activedescendant={open && activeId ? activeId : undefined}
        disabled={disabled}
        onClick={() => (open ? close() : openPanel())}
        onKeyDown={onKeyDown}
        onKeyUp={onKeyUp}
        onBlur={close}
        className={cn(fieldBox, className, picked && 'text-qb-ink-title', showsPlaceholder && 'text-qb-ink-subtle')}
        {...aria}
      >
        <span className="min-w-0 flex-1 truncate">{showsPlaceholder ? (placeholder ?? chosen?.label) : chosen?.label}</span>
        <ChevronDown
          aria-hidden="true"
          className={cn(
            'pointer-events-none absolute inset-y-0 end-4 my-auto size-5 text-qb-ink-subtle transition-transform duration-[180ms] ease-out motion-reduce:transition-none',
            open && 'rotate-180',
          )}
        />
      </button>
      {open && placement
        ? createPortal(
            // Pressing in the card keeps the focus on the field, so the field's blur does not close it before the click.
            <div
              ref={panelRef}
              style={placement.style}
              onMouseDown={(event) => event.preventDefault()}
              className={cn(panel.card, 'absolute z-50 max-w-[calc(100vw-2rem)] text-start font-qb')}
            >
              {drilled ? (
                <div role="presentation" className={panel.heading} onClick={() => leave(drilled.children[0])}>
                  <span aria-hidden="true">‹</span>
                  {drilled.label}
                </div>
              ) : null}
              <ul
                id={listId}
                role={nested ? 'tree' : 'listbox'}
                aria-label={label}
                className={cn(!nested && 'max-h-[412px] overflow-y-auto')}
              >
                {shownRows.map(renderRow)}
              </ul>
            </div>,
            document.body,
          )
        : null}
    </>
  );
}
