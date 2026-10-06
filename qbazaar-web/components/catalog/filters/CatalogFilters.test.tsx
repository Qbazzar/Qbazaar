import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { FilterSheet, FilterSidebar } from './CatalogFilters';
import { EMPTY_FILTERS } from './filter-values';

describe('FilterSidebar', () => {
  beforeEach(() => setClientLocale('en'));

  it('is a labelled region shown from desktop up', () => {
    render(<FilterSidebar groups={['price']} values={EMPTY_FILTERS} onApply={vi.fn()} onReset={vi.fn()} />);

    expect(screen.getByRole('complementary', { name: 'Filter' })).toHaveClass('hidden', 'qb-desktop:block');
    expect(screen.getByRole('heading', { level: 2, name: 'Filter' })).toBeInTheDocument();
  });
});

describe('FilterSheet', () => {
  beforeEach(() => setClientLocale('en'));

  it('counts the applied filters on its trigger', () => {
    render(<FilterSheet groups={['price']} values={{ ...EMPTY_FILTERS, priceMin: 10, location: 'doha' }} onApply={vi.fn()} onReset={vi.fn()} />);

    expect(screen.getByRole('button', { name: 'Filter 2 filters applied' })).toBeInTheDocument();
  });

  it('opens as a dialog and closes once the filters are applied', async () => {
    const onApply = vi.fn();
    const user = userEvent.setup();
    render(<FilterSheet groups={['price']} values={EMPTY_FILTERS} onApply={onApply} onReset={vi.fn()} />);

    await user.click(screen.getByRole('button', { name: 'Filter' }));
    const dialog = await screen.findByRole('dialog', { name: 'Filters' });
    await user.type(screen.getByLabelText('Maximum price (QAR)'), '900');
    await user.click(screen.getByRole('button', { name: 'Apply Filter' }));

    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, priceMax: 900 });
    await waitFor(() => expect(dialog).not.toBeInTheDocument());
  });
});
