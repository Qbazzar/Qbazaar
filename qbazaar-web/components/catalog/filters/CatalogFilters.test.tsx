import { useState } from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { ResultsFocusProvider } from '../results-focus';
import { ResultsHeading } from '../ResultsHeading';

import { FilterSheet, FilterSidebar } from './CatalogFilters';
import { EMPTY_FILTERS } from './filter-values';

describe('FilterSidebar', () => {
  beforeEach(() => setClientLocale('en'));

  it('is a labelled region shown from desktop up', () => {
    render(<FilterSidebar groups={['price']} values={EMPTY_FILTERS} onApply={vi.fn()} onReset={vi.fn()} />);

    expect(screen.getByRole('complementary', { name: 'Filter' })).toHaveClass('hidden', 'qb-desktop:block');
    expect(screen.getByRole('heading', { level: 2, name: 'Filter' })).toBeInTheDocument();
  });

  it('hands the focus to the results heading once the filters apply', async () => {
    const user = userEvent.setup();
    function Page() {
      const [values, setValues] = useState(EMPTY_FILTERS);
      return (
        <ResultsFocusProvider>
          <FilterSidebar groups={['price']} values={values} onApply={setValues} onReset={() => setValues(EMPTY_FILTERS)} />
          <ResultsHeading loading={false} total={2} />
        </ResultsFocusProvider>
      );
    }
    render(<Page />);

    await user.type(screen.getByLabelText('Minimum price (QAR)'), '100');
    await user.click(screen.getByRole('button', { name: 'Apply Filter' }));

    expect(screen.getByRole('heading', { level: 2, name: 'Results' })).toHaveFocus();
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
    const dialog = await screen.findByRole('dialog', { name: 'Advanced Filters' });
    await user.type(screen.getByLabelText('Maximum price (QAR)'), '900');
    await user.click(screen.getByRole('button', { name: 'Apply Filter' }));

    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, priceMax: 900 });
    await waitFor(() => expect(dialog).not.toBeInTheDocument());
  });
});
