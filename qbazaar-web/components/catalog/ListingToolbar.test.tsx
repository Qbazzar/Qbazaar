import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { ListingToolbar } from './ListingToolbar';

describe('ListingToolbar', () => {
  beforeEach(() => setClientLocale('en'));

  it('changes the sort from a labelled select', async () => {
    const onChange = vi.fn();
    render(<ListingToolbar sort={{ value: 'latest', onChange }} />);

    await userEvent.setup().selectOptions(screen.getByLabelText('Sort'), 'price_asc');

    expect(onChange).toHaveBeenCalledWith('price_asc');
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
