import { useState } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { FieldSelect, type FieldSelectOption } from './FieldSelect';
import { buildEntries, matchTyped, resolveKey } from './field-select-model';

const PRICE_TYPES: FieldSelectOption[] = [
  { value: 'fixed', label: 'Fixed Price' },
  { value: 'negotiable', label: 'Negotiable' },
  { value: 'free', label: 'Free' },
];

const CITIES: FieldSelectOption[] = [
  {
    value: 'doha',
    label: 'Doha',
    children: [
      { value: 'west-bay', label: 'West Bay' },
      { value: 'al-sadd', label: 'Al Sadd' },
    ],
  },
  { value: 'lusail', label: 'Lusail' },
];

function Harness({
  options = PRICE_TYPES,
  initial = 'fixed',
  placeholder,
  onChange = () => {},
}: {
  options?: FieldSelectOption[];
  initial?: string;
  placeholder?: string;
  onChange?: (value: string) => void;
}) {
  const [value, setValue] = useState(initial);
  return (
    <>
      <label htmlFor="field">Price Type</label>
      <FieldSelect
        id="field"
        label="Price Type"
        value={value}
        options={options}
        placeholder={placeholder}
        parentLabel={(city) => `${city.label} (all areas)`}
        onChange={(next) => {
          setValue(next);
          onChange(next);
        }}
      />
      <p>Outside</p>
    </>
  );
}

const field = () => screen.getByRole('combobox', { name: 'Price Type' });

describe('FieldSelect', () => {
  it('opens the designed list under the field and writes the chosen option into it', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    expect(field()).toHaveTextContent('Fixed Price');
    expect(field()).toHaveAttribute('aria-expanded', 'false');

    await userEvent.click(field());
    const list = screen.getByRole('listbox', { name: 'Price Type' });
    expect(field()).toHaveAttribute('aria-expanded', 'true');
    expect(field()).toHaveAttribute('aria-controls', list.id);
    expect(screen.getByRole('option', { name: 'Fixed Price' })).toHaveAttribute('aria-selected', 'true');

    await userEvent.click(screen.getByRole('option', { name: 'Negotiable' }));
    expect(onChange).toHaveBeenCalledWith('negotiable');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(field()).toHaveTextContent('Negotiable');
    expect(field()).toHaveClass('text-qb-ink-title');
  });

  it('scrolls the chosen row into view on a pointer open without lighting it', async () => {
    const scrollIntoView = vi.fn();
    Element.prototype.scrollIntoView = scrollIntoView;
    render(<Harness initial="free" />);

    await userEvent.click(field());

    const chosen = screen.getByRole('option', { name: 'Free' });
    expect(scrollIntoView).toHaveBeenCalledTimes(1);
    expect(scrollIntoView.mock.instances[0]).toBe(chosen);
    expect(field()).not.toHaveAttribute('aria-activedescendant');
    expect(chosen).not.toHaveClass('bg-(--color-qb-menu-active)');
    // @ts-expect-error jsdom has no scrollIntoView; the test installed it
    delete Element.prototype.scrollIntoView;
  });

  it('moves with the arrow keys, chooses with Enter and closes with Escape', () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);

    fireEvent.keyDown(field(), { key: 'ArrowDown' });
    expect(field()).toHaveAttribute('aria-activedescendant', screen.getByRole('option', { name: 'Fixed Price' }).id);
    fireEvent.keyDown(field(), { key: 'ArrowDown' });
    expect(field()).toHaveAttribute('aria-activedescendant', screen.getByRole('option', { name: 'Negotiable' }).id);
    fireEvent.keyDown(field(), { key: 'Escape' });
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();

    fireEvent.keyDown(field(), { key: 'ArrowDown' });
    fireEvent.keyDown(field(), { key: 'End' });
    fireEvent.keyDown(field(), { key: 'Enter' });
    expect(onChange).toHaveBeenCalledWith('free');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('jumps to the row that starts with the typed letters', () => {
    render(<Harness />);
    fireEvent.keyDown(field(), { key: 'n' });
    expect(field()).toHaveAttribute('aria-activedescendant', screen.getByRole('option', { name: 'Negotiable' }).id);
  });

  it('closes on a press outside without choosing', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    await userEvent.click(field());
    await userEvent.click(screen.getByText('Outside'));
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('shows the placeholder until a value is chosen', () => {
    render(<Harness options={CITIES} initial="" placeholder="City" />);
    expect(field()).toHaveTextContent('City');
    expect(field()).toHaveClass('text-qb-ink-subtle');
  });

  it('opens a city to reach its districts, by touch and with the arrow keys', () => {
    const onChange = vi.fn();
    render(<Harness options={CITIES} initial="" placeholder="City" onChange={onChange} />);

    fireEvent.click(field());
    const doha = screen.getByRole('treeitem', { name: 'Doha' });
    fireEvent.pointerDown(doha.firstElementChild!, { pointerType: 'touch' });
    fireEvent.click(doha.firstElementChild!);
    expect(onChange).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('treeitem', { name: 'Al Sadd' }));
    expect(onChange).toHaveBeenLastCalledWith('al-sadd');

    fireEvent.keyDown(field(), { key: 'ArrowDown' });
    expect(field()).toHaveAttribute('aria-activedescendant', screen.getByRole('treeitem', { name: 'Al Sadd' }).id);
    fireEvent.keyDown(field(), { key: 'ArrowUp' });
    fireEvent.keyDown(field(), { key: 'ArrowUp' });
    expect(field()).toHaveAttribute('aria-activedescendant', screen.getByRole('treeitem', { name: 'Doha (all areas)' }).id);
    fireEvent.keyDown(field(), { key: 'ArrowLeft' });
    expect(field()).toHaveAttribute('aria-activedescendant', screen.getByRole('treeitem', { name: 'Doha' }).id);
    fireEvent.keyDown(field(), { key: 'ArrowRight' });
    fireEvent.keyDown(field(), { key: 'Enter' });
    expect(onChange).toHaveBeenLastCalledWith('doha');
  });

  it('is a listbox of options for a flat list and a tree only for the two-level cascade', () => {
    const { unmount } = render(<Harness />);
    fireEvent.click(field());
    expect(field()).toHaveAttribute('aria-haspopup', 'listbox');
    expect(screen.getAllByRole('option')).toHaveLength(3);
    expect(screen.queryByRole('tree')).not.toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Fixed Price' })).not.toHaveAttribute('aria-level');
    unmount();

    render(<Harness options={CITIES} initial="" placeholder="City" />);
    fireEvent.click(field());
    expect(field()).toHaveAttribute('aria-haspopup', 'tree');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    const doha = screen.getByRole('treeitem', { name: 'Doha' });
    expect(doha).toHaveAttribute('aria-level', '1');
    expect(doha).toHaveAttribute('aria-setsize', '2');
    expect(doha).toHaveAttribute('aria-posinset', '1');
    expect(doha).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('treeitem', { name: 'Lusail' })).not.toHaveAttribute('aria-expanded');
  });

  it('describes the second level as a group of level 2 items inside its parent', () => {
    // jsdom has no layout, so give the page the width the second card needs beside the first.
    vi.spyOn(document.documentElement, 'clientWidth', 'get').mockReturnValue(1200);
    render(<Harness options={CITIES} initial="" placeholder="City" />);
    fireEvent.keyDown(field(), { key: 'ArrowDown' });
    fireEvent.keyDown(field(), { key: 'ArrowRight' });

    const doha = screen.getByRole('treeitem', { name: 'Doha' });
    expect(doha).toHaveAttribute('aria-expanded', 'true');
    expect(doha).toContainElement(screen.getByRole('group'));
    const westBay = screen.getByRole('treeitem', { name: 'West Bay' });
    expect(westBay).toHaveAttribute('aria-level', '2');
    expect(westBay).toHaveAttribute('aria-setsize', '3');
    expect(westBay).toHaveAttribute('aria-posinset', '2');
  });

  it('mirrors the arrow keys that enter and leave a second card in a right-to-left field', () => {
    const style = document.createElement('style');
    style.textContent = 'button[role="combobox"] { direction: rtl; }';
    document.head.appendChild(style);
    try {
      render(<Harness options={CITIES} initial="" placeholder="City" />);
      fireEvent.keyDown(field(), { key: 'ArrowDown' });
      const doha = screen.getByRole('treeitem', { name: 'Doha' });
      expect(field()).toHaveAttribute('aria-activedescendant', doha.id);

      fireEvent.keyDown(field(), { key: 'ArrowRight' });
      expect(field()).toHaveAttribute('aria-activedescendant', doha.id);
      fireEvent.keyDown(field(), { key: 'ArrowLeft' });
      expect(field()).toHaveAttribute('aria-activedescendant', screen.getByRole('treeitem', { name: 'Doha (all areas)' }).id);
      fireEvent.keyDown(field(), { key: 'ArrowRight' });
      expect(field()).toHaveAttribute('aria-activedescendant', doha.id);
    } finally {
      style.remove();
    }
  });

  it('does not open, and takes no key, while disabled', async () => {
    render(
      <>
        <label htmlFor="field">Price Type</label>
        <FieldSelect id="field" label="Price Type" value="fixed" options={PRICE_TYPES} onChange={() => {}} disabled />
      </>,
    );
    expect(field()).toBeDisabled();
    await userEvent.click(field());
    fireEvent.keyDown(field(), { key: 'ArrowDown' });
    expect(field()).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('closes when the field loses the focus, without choosing', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    await userEvent.click(field());
    expect(screen.getByRole('listbox')).toBeInTheDocument();

    fireEvent.blur(field());
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('keeps the card under its field when a scrolling parent moves the field', async () => {
    let fieldTop = 100;
    render(<Harness />);
    vi.spyOn(field(), 'getBoundingClientRect').mockImplementation(
      () => ({ top: fieldTop, bottom: fieldTop + 52, left: 10, right: 310, width: 300, height: 52, x: 10, y: fieldTop, toJSON: () => ({}) }) as DOMRect,
    );
    await userEvent.click(field());
    const card = () => screen.getByRole('listbox').parentElement as HTMLElement;
    expect(card().style.top).toBe('166px');

    fieldTop = 40;
    fireEvent.scroll(document.body);
    await waitFor(() => expect(card().style.top).toBe('106px'));
  });
});

describe('field-select-model', () => {
  const entries = () => buildEntries('f', CITIES, (city) => `${city.label} (all areas)`);

  it('adds a row that chooses the parent itself, which the field never shows as its value', () => {
    const { top, byValue } = entries();
    expect(top[0].children.map((child) => child.label)).toEqual(['Doha (all areas)', 'West Bay', 'Al Sadd']);
    expect(byValue.get('doha')).toBe(top[0]);
  });

  it('turns Left and Right around in right-to-left text', () => {
    const { top } = entries();
    expect(resolveKey('ArrowRight', top, top[0], false)).toEqual({ type: 'enter' });
    expect(resolveKey('ArrowLeft', top, top[0], false)).toEqual({ type: 'leave' });
    expect(resolveKey('ArrowLeft', top, top[0], true)).toEqual({ type: 'enter' });
    expect(resolveKey('ArrowRight', top, top[0], true)).toEqual({ type: 'leave' });
    expect(resolveKey('x', top, top[0], false)).toBeNull();
  });

  it('stays inside the list at both ends', () => {
    const { top } = entries();
    expect(resolveKey('ArrowDown', top, top[1], false)).toEqual({ type: 'highlight', entry: top[1] });
    expect(resolveKey('ArrowUp', top, top[0], false)).toEqual({ type: 'highlight', entry: top[0] });
    expect(resolveKey(' ', top, top[0], false)).toEqual({ type: 'choose', viaSpace: true });
  });

  it('joins letters typed within half a second and starts again after that', () => {
    const { top } = entries();
    const first = matchTyped('l', top, undefined, { text: '', at: 0 }, 1000);
    expect(first.entry).toBe(top[1]);
    const joined = matchTyped('u', top, top[1], first.next, 1200);
    expect(joined.next.text).toBe('lu');
    const later = matchTyped('d', top, top[1], joined.next, 2000);
    expect(later.next.text).toBe('d');
    expect(later.entry).toBe(top[0]);
  });
});
