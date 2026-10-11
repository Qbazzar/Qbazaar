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
    screen.getByRole('slider', { name: 'Minimum price (QAR)' }).focus();
    await user.keyboard('{ArrowRight}{ArrowRight}');
    await user.click(screen.getByRole('radio', { name: 'Used (7)' }));
    expect(onApply).not.toHaveBeenCalled();

    await user.click(apply);
    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, priceMin: 100, condition: 'used' });
  });

  it('shows the price range on the card as a slider with each handle’s price', () => {
    renderPanel({ values: { ...EMPTY_FILTERS, priceMin: 100, priceMax: 1000 } });

    expect(screen.getByRole('slider', { name: 'Minimum price (QAR)' })).toHaveAttribute('aria-valuetext', 'QAR 100');
    expect(screen.getByRole('slider', { name: 'Maximum price (QAR)' })).toHaveAttribute('aria-valuetext', 'QAR 1,000');
  });

  it('keeps "Apply Filter" solid while filters are applied', () => {
    renderPanel({ values: { ...EMPTY_FILTERS, condition: 'new' } });

    expect(screen.getByRole('button', { name: 'Apply Filter' })).toBeEnabled();
  });

  it('keeps the sheet’s "Apply Filter" solid with nothing chosen, and closes the sheet from it', async () => {
    const onKeep = vi.fn();
    const { onApply, user } = renderPanel({ variant: 'sheet', onKeep });
    const apply = screen.getByRole('button', { name: 'Apply Filter' });

    expect(apply).toBeEnabled();
    expect(apply).toHaveClass('font-bold');
    await user.click(apply);
    expect(onKeep).toHaveBeenCalledOnce();
    expect(onApply).not.toHaveBeenCalled();
  });

  it('leads the sheet with the fields of the design, then the radio groups', () => {
    renderPanel({ variant: 'sheet', groups: ['category', 'price', 'location', 'condition'] });
    const headings = screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent);

    expect(headings).toEqual(['Price Range (QAR)', 'City / Region', 'Category', 'Condition']);
  });

  it('sets the city names in the medium weight of the reference', () => {
    renderPanel();

    expect(screen.getByText('Al Wakrah')).toHaveClass('font-medium');
  });

  it('lists only the real options of a group, without an "any" row', () => {
    renderPanel();

    const condition = screen.getByRole('radiogroup', { name: 'Condition' });
    expect(within(condition).getAllByRole('radio').map((radio) => radio.closest('label')?.textContent)).toEqual(['Brand new (2)', 'Like new', 'Used (7)']);
    expect(within(condition).getAllByRole('radio').every((radio) => !(radio as HTMLInputElement).checked)).toBe(true);
  });

  it('lists the cities as radios with their counts on the card', async () => {
    const { onApply, user } = renderPanel();
    const cities = screen.getByRole('radiogroup', { name: 'Cities' });

    expect(within(cities).getByRole('radio', { name: 'Doha (4)' })).toBeInTheDocument();
    await user.click(within(cities).getByRole('radio', { name: 'Al Wakrah (1)' }));
    await user.click(screen.getByRole('button', { name: 'Apply Filter' }));

    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, location: 'al-wakrah' });
  });

  it('keeps an applied district listed on the card', () => {
    renderPanel({ values: { ...EMPTY_FILTERS, location: 'west-bay' } });

    expect(within(screen.getByRole('radiogroup', { name: 'Cities' })).getByRole('radio', { name: 'West Bay, Doha (4)' })).toBeChecked();
  });

  it('picks a district from the search box of the sheet’s "City / Region" select', async () => {
    const { onApply, user } = renderPanel({ variant: 'sheet' });
    const select = screen.getByRole('combobox', { name: 'City / Region' });

    expect(select).toHaveTextContent('All Regions');
    await user.click(select);
    const list = await screen.findByRole('listbox');
    expect(within(list).getAllByRole('option').map((option) => option.textContent)).toEqual(['Doha (4)', 'Al Wakrah (1)']);

    await user.type(screen.getByRole('combobox', { name: 'Search the options' }), 'west');
    await user.click(await screen.findByRole('option', { name: 'West Bay, Doha (4)' }));
    expect(select).toHaveTextContent('West Bay, Doha (4)');

    await user.click(screen.getByRole('button', { name: 'Apply Filter' }));
    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, location: 'west-bay' });
  });

  it('announces a maximum below the minimum and blocks applying it', async () => {
    const { onApply, user } = renderPanel({ variant: 'sheet', values: { ...EMPTY_FILTERS, priceMin: 500 } });
    const max = screen.getByLabelText('Maximum price (QAR)');

    await user.type(max, '100');

    expect(screen.getByRole('alert')).toHaveTextContent('The maximum price can’t be lower than the minimum.');
    expect(max).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('button', { name: 'Apply Filter' })).toBeDisabled();
    expect(onApply).not.toHaveBeenCalled();
  });

  it('labels each radio group by its heading and collapses it from the toggle', async () => {
    const { user } = renderPanel();
    const toggle = screen.getByRole('button', { name: 'Condition' });

    expect(screen.getByRole('radiogroup', { name: 'Condition' })).toBeInTheDocument();
    await user.click(toggle);

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('radiogroup', { name: 'Condition' })).toBeNull();
  });

  it('collapses the radio groups of the sheet too, while its fields keep plain labels', async () => {
    const { user } = renderPanel({ variant: 'sheet' });
    const toggle = screen.getByRole('button', { name: 'Condition' });

    expect(screen.queryByRole('button', { name: 'City / Region' })).toBeNull();
    expect(screen.getByText('Price Range (QAR)')).toBeInTheDocument();
    await user.click(toggle);

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
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

  it('does not reapply the same filters from the keyboard, but tells the sheet to close', async () => {
    const onKeep = vi.fn();
    const { onApply, user } = renderPanel({ variant: 'sheet', values: { ...EMPTY_FILTERS, priceMin: 100 }, onKeep });

    await user.type(screen.getByLabelText('Minimum price (QAR)'), '{Enter}');

    expect(onApply).not.toHaveBeenCalled();
    expect(onKeep).toHaveBeenCalledOnce();
  });

  it('only clears the draft from "Reset All" when nothing is applied', async () => {
    const { onReset, user } = renderPanel({ variant: 'sheet' });
    const min = screen.getByLabelText('Minimum price (QAR)');

    await user.type(min, '100');
    await user.click(screen.getByRole('button', { name: 'Reset All' }));

    expect(min).toHaveValue(null);
    expect(onReset).not.toHaveBeenCalled();
  });

  it('restarts its draft from newly applied filters without remounting', async () => {
    const user = userEvent.setup();
    const props = { variant: 'sheet' as const, groups: ['price' as const], onApply: vi.fn(), onReset: vi.fn() };
    const { rerender } = render(<FilterPanel {...props} values={EMPTY_FILTERS} />);
    const min = screen.getByLabelText('Minimum price (QAR)');

    await user.type(min, '5');
    rerender(<FilterPanel {...props} values={{ ...EMPTY_FILTERS, priceMin: 100 }} />);

    expect(screen.getByLabelText('Minimum price (QAR)')).toBe(min);
    expect(min).toHaveValue(100);
    expect(min).toHaveFocus();
  });
});
