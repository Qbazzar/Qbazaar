import { useState } from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { SelectMenu, type SelectMenuOption } from './SelectMenu';

const CATEGORIES: SelectMenuOption[] = [
  {
    value: 'vehicles',
    label: 'Vehicles',
    children: [
      { value: 'cars', label: 'Cars' },
      { value: 'boats', label: 'Boats' },
    ],
  },
  { value: 'jobs', label: 'Jobs', children: [{ value: 'part-time', label: 'Part-time' }] },
  { value: 'pets', label: 'Pets' },
];
const ALL = { value: '', label: 'All Categories' };
const DISTANCES: SelectMenuOption[] = [
  { value: '', label: 'All Qatar' },
  { value: '5', label: '+5 km' },
  { value: '10', label: '+10 km' },
];

function Harness({ nested = true, onChange = vi.fn() }: { nested?: boolean; onChange?: (option: SelectMenuOption) => void }) {
  const [value, setValue] = useState<string | null>(null);
  return (
    <>
      <SelectMenu
        label={nested ? 'Choose Category' : 'Distance'}
        heading={nested ? ALL : undefined}
        options={nested ? CATEGORIES : DISTANCES}
        value={value}
        onChange={(option) => {
          setValue(option.value);
          onChange(option);
        }}
        valueClassName="chosen"
      />
      <button type="button">Outside</button>
    </>
  );
}

const field = (name = 'Choose Category') => screen.getByRole('combobox', { name });
const activeRow = (combobox: HTMLElement) => document.getElementById(combobox.getAttribute('aria-activedescendant') ?? '');

describe('SelectMenu', () => {
  it('opens a tree of the categories under the field and closes it again from the field', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    expect(field()).toHaveAttribute('aria-expanded', 'false');
    expect(field()).toHaveTextContent('Choose Category');

    await user.click(field());
    const tree = screen.getByRole('tree', { name: 'Choose Category' });
    expect(field()).toHaveAttribute('aria-expanded', 'true');
    expect(field()).toHaveAttribute('aria-haspopup', 'tree');
    expect(within(tree).getByRole('treeitem', { name: 'All Categories' })).toBeInTheDocument();
    expect(within(tree).getByRole('treeitem', { name: 'Vehicles' })).toHaveAttribute('aria-expanded', 'false');
    expect(within(tree).getByRole('treeitem', { name: 'Pets' })).not.toHaveAttribute('aria-expanded');

    await user.click(field());
    expect(screen.queryByRole('tree')).toBeNull();
  });

  it('shows the children of a hovered row beside it and hides them over a row without any', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    await user.click(field());

    await user.hover(screen.getByText('Vehicles'));
    const vehicles = screen.getByRole('treeitem', { name: 'Vehicles' });
    expect(vehicles).toHaveAttribute('aria-expanded', 'true');
    expect(within(vehicles).getByRole('group')).toHaveTextContent('CarsBoats');

    await user.hover(screen.getByText('Pets'));
    expect(screen.queryByRole('group')).toBeNull();
  });

  it('picks a sub-category with a click, shows it in the field and closes', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    await user.click(field());

    await user.hover(screen.getByText('Vehicles'));
    await user.click(screen.getByRole('treeitem', { name: 'Boats' }));

    expect(onChange).toHaveBeenCalledWith({ value: 'boats', label: 'Boats' });
    expect(screen.queryByRole('tree')).toBeNull();
    expect(field()).toHaveTextContent('Boats');
    expect(screen.getByText('Boats')).toHaveClass('chosen');
    expect(field()).toHaveFocus();
  });

  it('picks the parent row itself, or everything from the heading', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);

    await user.click(field());
    await user.click(screen.getByText('Vehicles'));
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ value: 'vehicles' }));

    await user.click(field());
    await user.click(screen.getByText('All Categories'));
    expect(onChange).toHaveBeenLastCalledWith(ALL);
    expect(field()).toHaveTextContent('All Categories');
  });

  it('walks both levels with the arrow keys and picks with Enter', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    field().focus();

    await user.keyboard('{ArrowDown}');
    expect(screen.getByRole('tree')).toBeInTheDocument();
    expect(activeRow(field())).toHaveTextContent('All Categories');

    await user.keyboard('{ArrowDown}');
    expect(activeRow(field())).toHaveAccessibleName('Vehicles');
    expect(screen.getByRole('treeitem', { name: 'Vehicles' })).toHaveAttribute('aria-expanded', 'true');

    await user.keyboard('{ArrowRight}');
    expect(activeRow(field())).toHaveTextContent('Cars');
    await user.keyboard('{End}');
    expect(activeRow(field())).toHaveTextContent('Boats');
    await user.keyboard('{ArrowLeft}');
    expect(activeRow(field())).toHaveAccessibleName('Vehicles');

    await user.keyboard('{ArrowRight}{Enter}');
    expect(onChange).toHaveBeenCalledWith({ value: 'cars', label: 'Cars' });
    expect(screen.queryByRole('tree')).toBeNull();
  });

  it('reopens on the chosen option, with its parent open', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    await user.click(field());
    await user.hover(screen.getByText('Jobs'));
    await user.click(screen.getByText('Part-time'));

    await user.click(field());
    expect(screen.getByRole('treeitem', { name: 'Jobs' })).toHaveAttribute('aria-expanded', 'true');
    expect(activeRow(field())).toHaveTextContent('Part-time');
    expect(screen.getByRole('treeitem', { name: 'Part-time' })).toHaveAttribute('aria-selected', 'true');
  });

  it('closes on Escape, on a click outside and when the focus leaves', async () => {
    const user = userEvent.setup();
    render(<Harness />);

    await user.click(field());
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('tree')).toBeNull();
    expect(field()).toHaveFocus();

    await user.click(field());
    await user.click(document.body);
    expect(screen.queryByRole('tree')).toBeNull();

    await user.click(field());
    await user.tab();
    expect(screen.queryByRole('tree')).toBeNull();
    expect(screen.getByRole('button', { name: 'Outside' })).toHaveFocus();
  });

  it('lists flat options as a listbox, highlighting the first one when nothing is chosen', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Harness nested={false} onChange={onChange} />);

    await user.click(field('Distance'));
    const listbox = screen.getByRole('listbox', { name: 'Distance' });
    expect(within(listbox).getAllByRole('option')).toHaveLength(3);
    expect(activeRow(field('Distance'))).toHaveTextContent('All Qatar');

    await user.keyboard('{ArrowDown}{ArrowDown}{ArrowDown}{Enter}');
    expect(onChange).toHaveBeenCalledWith({ value: '10', label: '+10 km' });
    expect(field('Distance')).toHaveTextContent('+10 km');
  });

  it('says so while the options are still loading', async () => {
    const user = userEvent.setup();
    render(<SelectMenu label="Choose Category" heading={ALL} options={[]} value={null} onChange={vi.fn()} loadingLabel="Loading…" />);

    await user.click(field());
    expect(screen.getByRole('status')).toHaveTextContent('Loading…');
  });

  it('asks for the options as soon as the pointer or the focus reaches the field', async () => {
    const user = userEvent.setup();
    const onIntent = vi.fn();
    render(<SelectMenu label="Choose Category" options={CATEGORIES} value={null} onChange={vi.fn()} onIntent={onIntent} />);

    await user.hover(field());
    expect(onIntent).toHaveBeenCalled();
  });
});
