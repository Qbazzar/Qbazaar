import { useState } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { FieldSelect, type FieldSelectOption } from './FieldSelect';

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
});
