import type { CSSProperties, KeyboardEvent } from 'react';

export interface FieldSelectOption {
  value: string;
  label: string;
  /** A second level, listed in a card beside the row (in its place where there is no room). One level deep. */
  children?: readonly FieldSelectOption[];
}

/** An option as the open list knows it: with a DOM id and its place in the two levels. */
export interface Entry {
  id: string;
  value: string;
  label: string;
  parent: Entry | null;
  children: Entry[];
}

export interface Placement {
  style: CSSProperties;
  /** The second card fits beside the first; otherwise a parent's list replaces the first. */
  beside: boolean;
}

/** selects.js portal(): the card opens 14 px under the field, at least 190 px or the field's width. */
const PANEL_GAP = 14;
const PANEL_MIN_WIDTH = 190;
/** `.qb-ddsub`: the second card, 14 px beside the first and at least 260 px wide. */
const SUB_GAP = 14;
const SUB_MIN_WIDTH = 260;
export const TYPEAHEAD_RESET_MS = 500;

export interface Entries {
  top: Entry[];
  byId: Map<string, Entry>;
  byValue: Map<string, Entry>;
}

/**
 * Turns the options into entries. A parent gets an extra first child that
 * chooses the parent itself (named by `parentLabel`), which is not looked up
 * by value, so the field keeps showing the parent's own label.
 */
export function buildEntries(
  baseId: string,
  options: readonly FieldSelectOption[],
  parentLabel?: (option: FieldSelectOption) => string,
): Entries {
  const top: Entry[] = [];
  const byId = new Map<string, Entry>();
  const byValue = new Map<string, Entry>();
  const add = (entry: Entry, lookup: boolean) => {
    byId.set(entry.id, entry);
    if (lookup && !byValue.has(entry.value)) byValue.set(entry.value, entry);
    return entry;
  };
  const make = (id: string, value: string, label: string, parent: Entry | null): Entry => ({ id, value, label, parent, children: [] });

  options.forEach((option, index) => {
    const entry = add(make(`${baseId}-${index}`, option.value, option.label, null), true);
    const children = option.children ?? [];
    if (children.length > 0 && parentLabel) {
      entry.children.push(add(make(`${entry.id}-all`, option.value, parentLabel(option), entry), false));
    }
    children.forEach((child, childIndex) => {
      entry.children.push(add(make(`${entry.id}-${childIndex}`, child.value, child.label, entry), true));
    });
    top.push(entry);
  });
  return { top, byId, byValue };
}

/** Where the portaled card goes, in document coordinates, and whether a second card fits beside it. */
export function measure(field: HTMLElement): Placement {
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

export function isPrintable(event: Pick<KeyboardEvent, 'key' | 'ctrlKey' | 'metaKey' | 'altKey'>): boolean {
  return event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey;
}

export interface TypedText {
  text: string;
  at: number;
}

/** The entry a typed letter, or the letters typed within half a second, jumps to; the new text is `next`. */
export function matchTyped(
  key: string,
  rows: Entry[],
  from: Entry | undefined,
  previous: TypedText,
  now: number,
): { entry: Entry | undefined; next: TypedText } {
  const text = (now - previous.at > TYPEAHEAD_RESET_MS ? '' : previous.text) + key.toLowerCase();
  const start = from ? rows.indexOf(from) + (text.length === 1 ? 1 : 0) : 0;
  const entry = [...rows.slice(start), ...rows.slice(0, start)].find((candidate) => candidate.label.toLowerCase().startsWith(text));
  return { entry, next: { text, at: now } };
}

export type KeyAction =
  | { type: 'highlight'; entry: Entry | undefined }
  | { type: 'enter' }
  | { type: 'leave' }
  | { type: 'choose'; viaSpace: boolean }
  | { type: 'close' };

/**
 * What a key does in the open list. The arrows that go into a second card and
 * back are mirrored in a right-to-left field: Left enters and Right leaves.
 */
export function resolveKey(key: string, level: Entry[], active: Entry | undefined, rtl: boolean): KeyAction | null {
  const index = active ? level.indexOf(active) : -1;
  switch (key) {
    case 'ArrowDown':
      return { type: 'highlight', entry: level[Math.min(level.length - 1, index + 1)] };
    case 'ArrowUp':
      return { type: 'highlight', entry: level[Math.max(0, index - 1)] };
    case 'Home':
      return { type: 'highlight', entry: level[0] };
    case 'End':
      return { type: 'highlight', entry: level[level.length - 1] };
    case rtl ? 'ArrowLeft' : 'ArrowRight':
      return { type: 'enter' };
    case rtl ? 'ArrowRight' : 'ArrowLeft':
      return { type: 'leave' };
    case 'Enter':
      return { type: 'choose', viaSpace: false };
    case ' ':
      return { type: 'choose', viaSpace: true };
    case 'Escape':
      return { type: 'close' };
    default:
      return null;
  }
}
