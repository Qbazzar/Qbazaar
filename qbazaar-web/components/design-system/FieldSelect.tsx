'use client';

import './field-select.css';

import {
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type KeyboardEvent,
  type PointerEvent,
} from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown } from 'lucide-react';

import { cn } from '@/lib/utils';

export interface FieldSelectOption {
  value: string;
  label: string;
  /** A second level, listed in a card beside the row (in its place where there is no room). One level deep. */
  children?: readonly FieldSelectOption[];
}

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

/** selects.js portal(): the card opens 14 px under the field, at least 190 px or the field's width. */
const PANEL_GAP = 14;
const PANEL_MIN_WIDTH = 190;
/** `.qb-ddsub`: the second card, 14 px beside the first and at least 260 px wide. */
const SUB_GAP = 14;
const SUB_MIN_WIDTH = 260;
const TYPEAHEAD_RESET_MS = 500;

/** The box of the design-system Input and Select (`controlBase` in Input.tsx). */
const fieldBox = [
  'relative flex h-[52px] w-full cursor-pointer items-center rounded-qb-md border border-qb-field-border bg-qb-surface ps-4 pe-11',
  'text-start font-qb text-qb-body text-qb-ink outline-none transition-colors',
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

interface Entry {
  id: string;
  value: string;
  label: string;
  parent: Entry | null;
  children: Entry[];
}

interface Placement {
  style: CSSProperties;
  /** The second card fits beside the first; otherwise a parent's list replaces the first. */
  beside: boolean;
}

function buildEntries(baseId: string, options: readonly FieldSelectOption[], parentLabel?: FieldSelectProps['parentLabel']) {
  const top: Entry[] = [];
  const byId = new Map<string, Entry>();
  const byValue = new Map<string, Entry>();
  const add = (entry: Entry, lookup: boolean) => {
    byId.set(entry.id, entry);
    if (lookup && !byValue.has(entry.value)) byValue.set(entry.value, entry);
    return entry;
  };

  options.forEach((option, index) => {
    const entry = add({ id: `${baseId}-${index}`, value: option.value, label: option.label, parent: null, children: [] }, true);
    const children = option.children ?? [];
    if (children.length > 0 && parentLabel) {
      entry.children.push(add({ id: `${entry.id}-all`, value: option.value, label: parentLabel(option), parent: entry, children: [] }, false));
    }
    children.forEach((child, childIndex) => {
      entry.children.push(add({ id: `${entry.id}-${childIndex}`, value: child.value, label: child.label, parent: entry, children: [] }, true));
    });
    top.push(entry);
  });
  return { top, byId, byValue };
}

function measure(field: HTMLElement): Placement {
  const rect = field.getBoundingClientRect();
  const viewport = document.documentElement.clientWidth;
  const rtl = getComputedStyle(field).direction === 'rtl';
  const room = rtl ? rect.left : viewport - rect.right;
  const style: CSSProperties = {
    top: rect.bottom + window.scrollY + PANEL_GAP,
    minWidth: Math.max(PANEL_MIN_WIDTH, rect.width),
  };
  if (rtl) style.right = viewport - rect.right;
  else style.left = rect.left + window.scrollX;
  return { style, beside: room >= SUB_GAP + SUB_MIN_WIDTH };
}

function isPrintable(event: KeyboardEvent): boolean {
  return event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey;
}

/**
 * A form field that opens the reference's designed dropdown card (selects.js)
 * instead of the platform list: the card is portaled under the field, the
 * chevron turns while it is open, and a row with children opens a second card
 * beside it, or in place of the first where there is no room. A select-only
 * combobox: focus stays on the field, the arrow keys move the highlighted row
 * (towards a second card and back too), letters jump to a row, Enter or Space
 * chooses, Escape, Tab or a press outside closes. Arabic mirrors the cards
 * and the arrow keys.
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
  const typed = useRef({ text: '', at: 0 });
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
    // The card is placed once, so a new layout width closes it; phones also resize as their toolbars slide while scrolling.
    const openWidth = window.innerWidth;
    const closeOnNewWidth = () => {
      if (window.innerWidth !== openWidth) dismiss();
    };
    document.addEventListener('pointerdown', closeOnOutsidePress);
    window.addEventListener('resize', closeOnNewWidth);
    return () => {
      document.removeEventListener('pointerdown', closeOnOutsidePress);
      window.removeEventListener('resize', closeOnNewWidth);
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

  const matchTyped = (key: string, rows: Entry[], from: Entry | undefined) => {
    const now = Date.now();
    const text = (now - typed.current.at > TYPEAHEAD_RESET_MS ? '' : typed.current.text) + key.toLowerCase();
    typed.current = { text, at: now };
    const start = from ? rows.indexOf(from) + (text.length === 1 ? 1 : 0) : 0;
    return [...rows.slice(start), ...rows.slice(0, start)].find((entry) => entry.label.toLowerCase().startsWith(text));
  };

  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (!open) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        openPanel();
      } else if (isPrintable(event)) {
        event.preventDefault();
        openPanel(matchTyped(event.key, top, chosen) ?? chosen ?? top[0]);
      }
      return;
    }
    if (event.key === 'Tab') {
      close();
      return;
    }
    const rtl = getComputedStyle(event.currentTarget).direction === 'rtl';
    const index = active ? level.indexOf(active) : -1;
    const highlight = (entry: Entry | undefined) => entry && setActiveId(entry.id);
    const actions: Record<string, () => void> = {
      ArrowDown: () => highlight(level[Math.min(level.length - 1, index + 1)]),
      ArrowUp: () => highlight(level[Math.max(0, index - 1)]),
      Home: () => highlight(level[0]),
      End: () => highlight(level[level.length - 1]),
      [rtl ? 'ArrowLeft' : 'ArrowRight']: () => enter(active),
      [rtl ? 'ArrowRight' : 'ArrowLeft']: () => leave(active),
      Enter: () => active && choose(active),
      ' ': () => {
        spaceChose.current = true;
        if (active) choose(active);
      },
      Escape: close,
    };
    const action = actions[event.key];
    if (action) {
      event.preventDefault();
      if (event.key === 'Escape') event.stopPropagation();
      action();
    } else if (isPrintable(event)) {
      event.preventDefault();
      highlight(matchTyped(event.key, level, active));
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

  const renderOption = (entry: Entry) => (
    <li
      key={entry.id}
      id={entry.id}
      role={itemRole}
      aria-selected={entry === chosen}
      className={cn(panel.row, isLit(entry) && panel.activeRow)}
      {...rowHandlers(entry)}
    >
      <span className="min-w-0 truncate">{entry.label}</span>
    </li>
  );

  const renderRow = (entry: Entry) => {
    if (entry.children.length === 0) return renderOption(entry);
    const showsChildren = entry === expanded;
    return (
      <li
        key={entry.id}
        id={entry.id}
        role={itemRole}
        aria-selected={entry === chosen}
        aria-expanded={showsChildren}
        aria-label={entry.label}
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
            {entry.children.map(renderOption)}
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
