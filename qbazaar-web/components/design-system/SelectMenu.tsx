'use client';

import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { ChevronDown } from 'lucide-react';

import { cn } from '@/lib/utils';

import { dropdownPanel } from './dropdown-panel';
import { Icon } from './Icon';
import { keepInViewport } from './keep-in-viewport';

export interface SelectMenuOption {
  value: string;
  label: string;
  /** A second level, listed in a card beside the row. One level deep. */
  children?: SelectMenuOption[];
}

export interface SelectMenuProps {
  /** Names the field and shows in it until a value is chosen. */
  label: string;
  options: SelectMenuOption[];
  /** A first row that chooses everything ("All Categories"), drawn as the list's heading. */
  heading?: SelectMenuOption;
  /** The chosen option's value; null shows the label. */
  value: string | null;
  onChange: (option: SelectMenuOption) => void;
  /** Said in the open panel while there are no options yet. */
  loadingLabel?: string;
  /** The pointer or the focus reached the field: a chance to load the options before the click. */
  onIntent?: () => void;
  /** Classes of the wrapper, the flex item of the surrounding bar. */
  className?: string;
  /** Classes of the field itself. */
  fieldClassName?: string;
  /** Size of the label and the chosen value. */
  textClassName?: string;
  placeholderClassName?: string;
  valueClassName?: string;
  chevronClassName?: string;
}

/** The heading has no hover state in the design, so it shows the highlight only to keyboard users. */
const keyboardOnlyActive = 'group-has-[[role=combobox]:focus-visible]/select:bg-qb-menu-active';

interface Entry {
  option: SelectMenuOption;
  id: string;
  parent?: Entry;
  children: Entry[];
}

function buildEntries(baseId: string, options: SelectMenuOption[], heading?: SelectMenuOption) {
  const top: Entry[] = heading ? [{ option: heading, id: `${baseId}-all`, children: [] }] : [];
  options.forEach((option, index) => {
    const entry: Entry = { option, id: `${baseId}-${index}`, children: [] };
    entry.children = (option.children ?? []).map((child, childIndex) => ({
      option: child,
      id: `${baseId}-${index}-${childIndex}`,
      parent: entry,
      children: [],
    }));
    top.push(entry);
  });
  const byValue = new Map<string, Entry>();
  top.forEach((entry) => {
    byValue.set(entry.option.value, entry);
    entry.children.forEach((child) => byValue.set(child.option.value, child));
  });
  return { top, byValue };
}

/**
 * The hero's select field (selects.js of the reference): a click opens a list
 * card under it; options with children open a second card beside their row
 * on hover or with the arrow key towards it. A select-only combobox: the
 * focus stays on the field and the arrow keys move the highlighted row.
 * Arabic mirrors the cards and the keys.
 */
export function SelectMenu({
  label,
  options,
  heading,
  value,
  onChange,
  loadingLabel,
  onIntent,
  className,
  fieldClassName,
  textClassName,
  placeholderClassName,
  valueClassName,
  chevronClassName,
}: SelectMenuProps) {
  const baseId = useId();
  const listId = `${baseId}-list`;
  const rootRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [activeValue, setActiveValue] = useState<string | null>(null);
  const [expandedValue, setExpandedValue] = useState<string | null>(null);

  const { top, byValue } = useMemo(() => buildEntries(baseId, options, heading), [baseId, options, heading]);
  const nested = top.some((entry) => entry.children.length > 0);
  const itemRole = nested ? 'treeitem' : 'option';
  const active = activeValue === null ? undefined : byValue.get(activeValue);
  const chosen = value === null ? undefined : byValue.get(value);

  useEffect(() => {
    if (!open) return;
    const closeOnOutsidePress = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('pointerdown', closeOnOutsidePress);
    return () => document.removeEventListener('pointerdown', closeOnOutsidePress);
  }, [open]);

  /** Highlights a row; on the first level that also shows its children, or hides the open ones. */
  const highlight = (entry: Entry | undefined) => {
    if (!entry) return;
    setActiveValue(entry.option.value);
    if (!entry.parent) setExpandedValue(entry.children.length > 0 ? entry.option.value : null);
  };

  const openMenu = () => {
    onIntent?.();
    setOpen(true);
    const start = chosen ?? top[0];
    setActiveValue(start?.option.value ?? null);
    const shown = start?.parent ?? start;
    setExpandedValue(shown && shown.children.length > 0 ? shown.option.value : null);
  };

  const choose = (entry: Entry) => {
    onChange(entry.option);
    setOpen(false);
  };

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (!open) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        openMenu();
      }
      return;
    }
    if (event.key === 'Tab') {
      setOpen(false);
      return;
    }
    const rtl = getComputedStyle(event.currentTarget).direction === 'rtl';
    const level = active?.parent ? active.parent.children : top;
    const index = active ? level.indexOf(active) : -1;
    const keys: Record<string, () => void> = {
      ArrowDown: () => highlight(level[Math.min(level.length - 1, index + 1)]),
      ArrowUp: () => highlight(level[Math.max(0, index - 1)]),
      Home: () => highlight(level[0]),
      End: () => highlight(level[level.length - 1]),
      [rtl ? 'ArrowLeft' : 'ArrowRight']: () => highlight(active?.children[0]),
      [rtl ? 'ArrowRight' : 'ArrowLeft']: () => highlight(active?.parent),
      Enter: () => active && choose(active),
      ' ': () => active && choose(active),
      Escape: () => setOpen(false),
    };
    const action = keys[event.key];
    if (!action) return;
    event.preventDefault();
    action();
  };

  const renderRow = (entry: Entry) => {
    const hasChildren = entry.children.length > 0;
    const expanded = hasChildren && expandedValue === entry.option.value;
    const look = cn(dropdownPanel.row, (entry === active || expanded) && dropdownPanel.activeRow);
    return (
      <li
        key={entry.id}
        id={entry.id}
        role={itemRole}
        aria-selected={entry === chosen}
        aria-expanded={hasChildren ? expanded : undefined}
        // A parent's name is its own label, not the text of the children inside it.
        aria-label={hasChildren ? entry.option.label : undefined}
        className={hasChildren ? 'relative' : look}
        onMouseEnter={() => highlight(entry)}
        onClick={() => choose(entry)}
      >
        {hasChildren ? (
          <div className={look}>
            <span>{entry.option.label}</span>
            <span aria-hidden="true" className={dropdownPanel.caret}>
              ›
            </span>
          </div>
        ) : (
          entry.option.label
        )}
        {expanded ? (
          <ul ref={keepInViewport} role="group" className={cn(dropdownPanel.card, dropdownPanel.subCard, 'top-0.5')}>
            {entry.children.map((child) => (
              <li
                key={child.id}
                id={child.id}
                role={itemRole}
                aria-selected={child === chosen}
                className={cn(dropdownPanel.row, child === active && dropdownPanel.activeRow)}
                onMouseEnter={() => highlight(child)}
                onClick={(event) => {
                  // The parent row around it would pick itself next.
                  event.stopPropagation();
                  choose(child);
                }}
              >
                {child.option.label}
              </li>
            ))}
          </ul>
        ) : null}
      </li>
    );
  };

  const headingEntry = heading ? top[0] : undefined;
  const rows = heading ? top.slice(1) : top;

  return (
    <div ref={rootRef} className={cn('group/select relative flex', className)} onPointerEnter={onIntent}>
      <div
        role="combobox"
        tabIndex={0}
        aria-label={label}
        aria-haspopup={nested ? 'tree' : 'listbox'}
        aria-expanded={open}
        aria-controls={open ? listId : undefined}
        aria-activedescendant={open ? active?.id : undefined}
        onClick={() => (open ? setOpen(false) : openMenu())}
        onKeyDown={onKeyDown}
        onFocus={onIntent}
        onBlur={() => setOpen(false)}
        className={cn('flex w-full cursor-pointer items-center justify-between', fieldClassName)}
      >
        {/* The hidden copy of the label keeps the field at least as wide as the label; a longer choice is cut short. */}
        <span className={cn('grid flex-1 text-start whitespace-nowrap', textClassName)}>
          <span aria-hidden="true" className="invisible col-start-1 row-start-1">
            {label}
          </span>
          <span className={cn('col-start-1 row-start-1 w-0 min-w-full truncate', chosen ? valueClassName : placeholderClassName)}>
            {chosen ? chosen.option.label : label}
          </span>
        </span>
        <Icon
          icon={ChevronDown}
          className={cn('transition-transform duration-[180ms] ease-out motion-reduce:transition-none', open && 'rotate-180', chevronClassName)}
        />
      </div>
      {open ? (
        // Pressing in the panel keeps the focus on the field, so the field's blur does not close it before the click.
        <div
          ref={keepInViewport}
          className={cn(dropdownPanel.card, dropdownPanel.placement)}
          onMouseDown={(event) => event.preventDefault()}
        >
          <ul id={listId} role={nested ? 'tree' : 'listbox'} aria-label={label}>
            {headingEntry ? (
              <li
                id={headingEntry.id}
                role={itemRole}
                aria-selected={headingEntry === chosen}
                className={cn(dropdownPanel.heading, headingEntry === active && keyboardOnlyActive)}
                onClick={() => choose(headingEntry)}
              >
                {headingEntry.option.label}
              </li>
            ) : null}
            {rows.map(renderRow)}
          </ul>
          {options.length === 0 && loadingLabel ? (
            <p role="status" className={dropdownPanel.row}>
              {loadingLabel}
            </p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
