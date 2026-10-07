import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { Location } from '@/lib/api/types';

import { EMPTY_FILTERS } from './filter-values';
import { FilterPanel } from './FilterPanel';

function place(slug: string, en: string, children: Location[] = []): Location {
  return { id: slug, parent_id: null, slug, name: { ar: en, en }, type: 'city', lat: null, lng: null, children };
}

const locations = [place('doha', 'Doha', [place('west-bay', 'West Bay')]), place('al-wakrah', 'Al Wakrah')];

function renderPanel(overrides: Partial<Parameters<typeof FilterPanel>[0]> = {}) {
  const onApply = vi.fn();
  const onReset = vi.fn();
  render(
    <FilterPanel
      variant="sidebar"
      groups={['price', 'location', 'condition']}
      values={EMPTY_FILTERS}
      onApply={onApply}
      onReset={onReset}
      locations={locations}
      facets={{ categories: {}, locations: { 'west-bay': 4, 'al-wakrah': 1 }, conditions: { new: 2, like_new: 0, used: 7 }, price_buckets: [] }}
      {...overrides}
    />,
  );
  return { onApply, onReset, user: userEvent.setup() };
}

describe('FilterPanel', () => {
  beforeEach(() => setClientLocale('en'));

  it('applies the edited draft only on "Apply Filter"', async () => {
    const { onApply, user } = renderPanel();
    const apply = screen.getByRole('button', { name: 'Apply Filter' });

    expect(apply).toBeDisabled();
    await user.type(screen.getByLabelText('Minimum price (QAR)'), '100');
    await user.click(screen.getByRole('radio', { name: 'Used (7)' }));
    expect(onApply).not.toHaveBeenCalled();

    await user.click(apply);
    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, priceMin: 100, condition: 'used' });
  });

  it('announces a maximum below the minimum and blocks applying it', async () => {
    const { onApply, user } = renderPanel({ values: { ...EMPTY_FILTERS, priceMin: 500 } });
    const max = screen.getByLabelText('Maximum price (QAR)');

    await user.type(max, '100');

    expect(screen.getByRole('alert')).toHaveTextContent('The maximum price can’t be lower than the minimum.');
    expect(max).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('button', { name: 'Apply Filter' })).toBeDisabled();
    expect(onApply).not.toHaveBeenCalled();
  });

  it('shows facet counts per city and offers the districts of the chosen city', async () => {
    const { onApply, user } = renderPanel();
    const city = screen.getByLabelText('City / Region');

    expect(within(city).getByRole('option', { name: 'Doha (4)' })).toBeInTheDocument();
    await user.selectOptions(city, 'doha');
    await user.selectOptions(screen.getByLabelText('District'), 'west-bay');
    await user.click(screen.getByRole('button', { name: 'Apply Filter' }));

    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, location: 'west-bay' });
  });

  it('labels each radio group by its heading and collapses it from the toggle', async () => {
    const { user } = renderPanel();
    const toggle = screen.getByRole('button', { name: 'Condition' });

    expect(screen.getByRole('radiogroup', { name: 'Condition' })).toBeInTheDocument();
    await user.click(toggle);

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('radiogroup', { name: 'Condition' })).toBeNull();
  });

  it('removes an applied filter at once from its chip', async () => {
    const { onApply, user } = renderPanel({ values: { ...EMPTY_FILTERS, location: 'al-wakrah', condition: 'new' } });

    expect(screen.getByRole('list', { name: 'Applied filters' })).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Remove Al Wakrah' }));

    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, condition: 'new' });
  });

  it('edits the search words with the filters on the search page', async () => {
    const { onApply, user } = renderPanel({ groups: ['keyword', 'condition'], values: { ...EMPTY_FILTERS, keyword: 'car' } });
    const keyword = screen.getByRole('searchbox', { name: 'Search' });

    expect(keyword).toHaveValue('car');
    await user.clear(keyword);
    await user.type(keyword, ' family van ');
    await user.click(screen.getByRole('button', { name: 'Apply Filter' }));

    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, keyword: 'family van' });
  });

  it('resets from "Reset All"', async () => {
    const { onReset, user } = renderPanel({ values: { ...EMPTY_FILTERS, condition: 'new' } });

    await user.click(screen.getByRole('button', { name: 'Reset All' }));

    expect(onReset).toHaveBeenCalledOnce();
  });

  it('does not reapply the same filters from the keyboard', async () => {
    const { onApply, user } = renderPanel({ values: { ...EMPTY_FILTERS, priceMin: 100 } });

    await user.type(screen.getByLabelText('Minimum price (QAR)'), '{Enter}');

    expect(onApply).not.toHaveBeenCalled();
  });

  it('only clears the draft from "Reset All" when nothing is applied', async () => {
    const { onReset, user } = renderPanel();
    const min = screen.getByLabelText('Minimum price (QAR)');

    await user.type(min, '100');
    await user.click(screen.getByRole('button', { name: 'Reset All' }));

    expect(min).toHaveValue(null);
    expect(onReset).not.toHaveBeenCalled();
  });

  it('restarts its draft from newly applied filters without remounting', async () => {
    const user = userEvent.setup();
    const props = { variant: 'sidebar' as const, groups: ['price' as const], onApply: vi.fn(), onReset: vi.fn() };
    const { rerender } = render(<FilterPanel {...props} values={EMPTY_FILTERS} />);
    const min = screen.getByLabelText('Minimum price (QAR)');

    await user.type(min, '5');
    rerender(<FilterPanel {...props} values={{ ...EMPTY_FILTERS, priceMin: 100 }} />);

    expect(screen.getByLabelText('Minimum price (QAR)')).toBe(min);
    expect(min).toHaveValue(100);
    expect(min).toHaveFocus();
  });
});
