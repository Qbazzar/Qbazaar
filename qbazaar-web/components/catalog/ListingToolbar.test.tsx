import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { ListingToolbar } from './ListingToolbar';

describe('ListingToolbar', () => {
  beforeEach(() => setClientLocale('en'));

  it('opens the designed sort menu in the reference order and picks a row', async () => {
    const onChange = vi.fn();
    const user = userEvent.setup();
    render(<ListingToolbar sort={{ value: 'latest', onChange }} />);
    const trigger = screen.getByRole('combobox', { name: 'Sort' });

    expect(trigger).toHaveTextContent('Newest');
    await user.click(trigger);
    const menu = await screen.findByRole('listbox');

    expect(within(menu).getAllByRole('option').map((option) => option.textContent)).toEqual(['Newest', 'Oldest', 'High Price', 'Low Price']);
    expect(within(menu).getByRole('option', { name: 'Newest' })).toHaveAttribute('aria-selected', 'true');
    await user.click(within(menu).getByRole('option', { name: 'Low Price' }));

    expect(onChange).toHaveBeenCalledWith('price_asc');
  });

  it('closes the sort menu on Escape without changing the sort', async () => {
    const onChange = vi.fn();
    const user = userEvent.setup();
    render(<ListingToolbar sort={{ value: 'price_desc', onChange }} />);
    const trigger = screen.getByRole('combobox', { name: 'Sort' });

    expect(trigger).toHaveTextContent('High Price');
    await user.click(trigger);
    await screen.findByRole('listbox');
    await user.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('listbox')).toBeNull());
    expect(trigger).toHaveAttribute('aria-expanded', 'false');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('toggles between the grid and list views', async () => {
    const onChange = vi.fn();
    render(<ListingToolbar view={{ value: 'list', onChange }} />);
    const grid = screen.getByRole('button', { name: 'Grid view' });

    expect(screen.getByRole('group', { name: 'View' })).toHaveClass('hidden', 'qb-desktop:flex');
    expect(screen.getByRole('button', { name: 'List view' })).toHaveAttribute('aria-pressed', 'true');
    expect(grid).toHaveAttribute('aria-pressed', 'false');

    await userEvent.setup().click(grid);
    expect(onChange).toHaveBeenCalledWith('grid');
  });

  it('puts the filter trigger and the page actions in the toolbar below desktop', () => {
    render(
      <ListingToolbar
        filters={<button type="button">Filter</button>}
        actions={<button type="button">Save Search</button>}
      />,
    );

    expect(screen.getByRole('button', { name: 'Filter' }).parentElement).toHaveClass('qb-desktop:hidden');
    expect(screen.getByRole('button', { name: 'Save Search' }).parentElement).toHaveClass('qb-desktop:hidden');
  });
});
