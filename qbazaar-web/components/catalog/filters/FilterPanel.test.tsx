import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { AdFacets, CategoryNode, Location } from '@/lib/api/types';

import { EMPTY_FILTERS } from './filter-values';
import { FilterPanel } from './FilterPanel';

function place(slug: string, en: string, children: Location[] = []): Location {
  return { id: slug, parent_id: null, slug, name: { ar: en, en }, type: 'city', lat: null, lng: null, children };
}

const locations = [place('doha', 'Doha', [place('west-bay', 'West Bay')]), place('al-wakrah', 'Al Wakrah')];

const ALL_FACETS: AdFacets = { ad_type: {}, shipping: {}, seller_type: {}, buy_now: {}, condition: {} };

/**
 * The sheet's orange bar, below the card's own "Apply Filter". It reads "Show Results" once the copy
 * pass adds the key and "Apply Filter" until then, so it is the last button of that name.
 */
const sheetBar = () => screen.getAllByRole('button', { name: /^(Show Results|Apply Filter)$/ }).at(-1)!;

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
      facets={{ categories: {}, locations: { 'west-bay': 4, 'al-wakrah': 1 }, conditions: { new: 0, like_new: 0, used: 0 }, price_buckets: [] }}
      adFacets={{ ...ALL_FACETS, condition: { new: 2, used: 7 } }}
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

    // Pale but still a button, as the reference's: it only applies once something changed.
    expect(apply).toHaveAttribute('aria-disabled', 'true');
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

  it('closes the sheet from its orange "Show Results" bar when nothing changed', async () => {
    const onKeep = vi.fn();
    const { onApply, user } = renderPanel({ variant: 'sheet', onKeep });
    const apply = sheetBar();

    expect(apply).toBeEnabled();
    expect(apply).toHaveClass('bg-qb-brand', 'font-semibold');
    await user.click(apply);
    expect(onKeep).toHaveBeenCalledOnce();
    expect(onApply).not.toHaveBeenCalled();
  });

  it('shows the card itself in the sheet: its groups in the same order, the slider and "Apply Filter"', () => {
    renderPanel({ variant: 'sheet', groups: ['category', 'price', 'location', 'condition'] });
    // The toggles' "−" is decorative (aria-hidden), so it is left out of the names.
    const headings = screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent?.replace(/[−+]$/, ''));

    expect(headings).toEqual(['Category', 'Price', 'Cities', 'Condition']);
    expect(screen.getByRole('slider', { name: 'Minimum price (QAR)' })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /^(Show Results|Apply Filter)$/ })[0]).toHaveAttribute('aria-disabled', 'true');
  });

  it('draws the sheet’s "Filter" as the funnel pill, which is no heading', () => {
    renderPanel({ variant: 'sheet' });
    const title = screen.getByText('Filter');

    expect(screen.queryByRole('heading', { level: 2 })).toBeNull();
    expect(title).toHaveClass('rounded-qb-md', 'border', 'font-semibold');
    expect(title.querySelector('svg')).not.toBeNull();
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

  it('picks a city from the radios of the sheet and applies it from the bar', async () => {
    const { onApply, user } = renderPanel({ variant: 'sheet' });

    await user.click(within(screen.getByRole('radiogroup', { name: 'Cities' })).getByRole('radio', { name: 'Al Wakrah (1)' }));
    await user.click(sheetBar());

    expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, location: 'al-wakrah' });
  });

  it('blocks the sheet’s bar while a price range is upside down', () => {
    renderPanel({ variant: 'sheet', values: { ...EMPTY_FILTERS, priceMin: 500, priceMax: 100 } });

    expect(sheetBar()).toBeDisabled();
  });

  it('labels each radio group by its heading and collapses it from the toggle', async () => {
    const { user } = renderPanel();
    const toggle = screen.getByRole('button', { name: 'Condition' });

    expect(screen.getByRole('radiogroup', { name: 'Condition' })).toBeInTheDocument();
    await user.click(toggle);

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('radiogroup', { name: 'Condition' })).toBeNull();
  });

  it('collapses the groups of the sheet from their toggles, as on the card', async () => {
    const { user } = renderPanel({ variant: 'sheet' });
    const toggle = screen.getByRole('button', { name: 'Cities' });

    // The price group has no toggle, in the sheet as on the card.
    expect(screen.queryByRole('button', { name: 'Price' })).toBeNull();
    await user.click(toggle);

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('radiogroup', { name: 'Cities' })).toBeNull();
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
    const { onApply, user } = renderPanel({ variant: 'sheet', groups: ['keyword', 'condition'], values: { ...EMPTY_FILTERS, keyword: 'car' }, onKeep });

    await user.type(screen.getByRole('searchbox', { name: 'Search' }), '{Enter}');

    expect(onApply).not.toHaveBeenCalled();
    expect(onKeep).toHaveBeenCalledOnce();
  });

  it('only clears the draft from "Reset All" when nothing is applied', async () => {
    const { onReset, user } = renderPanel({ variant: 'sheet' });
    const used = screen.getByRole('radio', { name: 'Used (7)' });

    await user.click(used);
    await user.click(screen.getByRole('button', { name: 'Reset All' }));

    expect(used).not.toBeChecked();
    expect(onReset).not.toHaveBeenCalled();
  });

  it('restarts its draft from newly applied filters without remounting', async () => {
    const user = userEvent.setup();
    const props = { variant: 'sheet' as const, groups: ['price' as const], onApply: vi.fn(), onReset: vi.fn() };
    const { rerender } = render(<FilterPanel {...props} values={EMPTY_FILTERS} />);
    const min = screen.getByRole('slider', { name: 'Minimum price (QAR)' });

    min.focus();
    await user.keyboard('{ArrowRight}');
    rerender(<FilterPanel {...props} values={{ ...EMPTY_FILTERS, priceMin: 100 }} />);

    expect(screen.getByRole('slider', { name: 'Minimum price (QAR)' })).toBe(min);
    expect(min).toHaveAttribute('aria-valuetext', 'QAR 100');
    expect(min).toHaveFocus();
  });
  describe('the offer, seller, shipping and Buy Now groups', () => {
    const groups: Parameters<typeof FilterPanel>[0]['groups'] = ['adType', 'sellerType', 'shipping', 'condition', 'buyNow'];
    const adFacets: AdFacets = {
      ad_type: { offering: 7592369, wanted: 95889 },
      shipping: { delivery: 3988056, pickup_only: 1722375 },
      seller_type: { private: 5163947, business: 2524311 },
      buy_now: { true: 12, false: 5 },
      condition: { new: 3 },
    };

    it('names every option with its count, as the design does', () => {
      renderPanel({ groups, adFacets });

      expect(screen.getByRole('radio', { name: 'Available (7,592,369)' })).toBeInTheDocument();
      expect(screen.getByRole('radio', { name: 'Wanted (95,889)' })).toBeInTheDocument();
      expect(screen.getByRole('radio', { name: 'Private (5,163,947)' })).toBeInTheDocument();
      expect(screen.getByRole('radio', { name: 'Dealer (2,524,311)' })).toBeInTheDocument();
      expect(screen.getByRole('radio', { name: 'Shipping Available (3,988,056)' })).toBeInTheDocument();
      expect(screen.getByRole('radio', { name: 'Local Pickup Only (1,722,375)' })).toBeInTheDocument();
      expect(screen.getByRole('checkbox', { name: 'Buy Now (12)' })).toBeInTheDocument();
    });

    it('labels each group by its heading', () => {
      renderPanel({ groups, adFacets });

      for (const name of ['Offer', 'Seller', 'Shipping & Delivery', 'Condition']) {
        expect(screen.getByRole('radiogroup', { name })).toBeInTheDocument();
      }
    });

    it('shows no number at all when the counts timed out', () => {
      renderPanel({ groups, adFacets: ALL_FACETS });

      expect(screen.getByRole('radio', { name: 'Available' })).toBeInTheDocument();
      expect(screen.getByRole('checkbox', { name: 'Buy Now' })).toBeInTheDocument();
      expect(screen.queryByText(/\(0\)/)).not.toBeInTheDocument();
    });

    it('shows only the chosen value once a filter narrows the counts', () => {
      renderPanel({ groups, values: { ...EMPTY_FILTERS, adType: 'wanted' }, adFacets: { ...adFacets, ad_type: { wanted: 40 } } });

      expect(screen.getByRole('radio', { name: 'Wanted (40)' })).toBeChecked();
      expect(screen.getByRole('radio', { name: 'Available' })).not.toBeChecked();
    });

    it('applies the seller, Buy Now and shipping choices together', async () => {
      const { onApply, user } = renderPanel({ groups, adFacets });

      await user.click(screen.getByRole('radio', { name: /^Dealer/ }));
      await user.click(screen.getByRole('checkbox', { name: /^Buy Now/ }));
      await user.click(screen.getByRole('radio', { name: /^Local Pickup Only/ }));
      await user.click(screen.getByRole('button', { name: 'Apply Filter' }));

      expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, sellerType: 'business', buyNow: true, shipping: 'pickup_only' });
    });

    it('removes a Buy Now chip at once', async () => {
      const { onApply, user } = renderPanel({ groups, values: { ...EMPTY_FILTERS, buyNow: true, sellerType: 'private' }, adFacets });

      await user.click(screen.getByRole('button', { name: 'Remove Buy Now' }));

      expect(onApply).toHaveBeenCalledWith({ ...EMPTY_FILTERS, sellerType: 'private' });
    });

    it('toggles Buy Now from the keyboard', async () => {
      const { user } = renderPanel({ groups, adFacets });

      screen.getByRole('checkbox', { name: /^Buy Now/ }).focus();
      await user.keyboard(' ');

      expect(screen.getByRole('checkbox', { name: /^Buy Now/ })).toBeChecked();
    });
  });

  it('counts the options of a category select from meta.facets.custom_fields', async () => {
    const category = {
      id: 'c1',
      slug: 'cars',
      name: { en: 'Cars', ar: 'سيارات' },
      children: [],
      custom_fields: [
        { key: 'brand', type: 'select', label: { en: 'Brand', ar: 'الماركة' }, options_labeled: [{ value: 'bmw', label: 'BMW' }, { value: 'audi', label: 'Audi' }] },
      ],
    } as unknown as CategoryNode;
    const { user } = renderPanel({
      groups: ['customFields'],
      categories: [category],
      categorySlug: 'cars',
      adFacets: { ...ALL_FACETS, custom_fields: { brand: { bmw: 1200 } } },
    });

    await user.click(screen.getByRole('combobox', { name: 'Brand' }));

    expect(await screen.findByRole('option', { name: 'BMW (1,200)' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Audi' })).toBeInTheDocument();
  });
});
