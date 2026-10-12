import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { Location } from '@/lib/api/types';

import { EMPTY_FILTERS } from './filter-values';
import { FilterSheet } from './CatalogFilters';
import { SelectField } from './SelectField';

const options = [
  { value: 'doha', label: 'Doha' },
  { value: 'al-wakrah', label: 'Al Wakrah' },
];

function setup(value: string | null = null) {
  const onChange = vi.fn();
  render(
    <div>
      <span id="lbl">City</span>
      <SelectField labelledBy="lbl" options={options} value={value} onChange={onChange} placeholder="All Regions" />
      <p>Outside</p>
    </div>,
  );
  return { onChange, user: userEvent.setup(), trigger: screen.getByRole('combobox', { name: 'City' }) };
}

describe('SelectField', () => {
  beforeEach(() => setClientLocale('en'));

  it('closes on Escape and returns the focus to the trigger', async () => {
    const { user, trigger } = setup();

    await user.click(trigger);
    expect(await screen.findByRole('listbox')).toBeInTheDocument();
    await user.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
    expect(trigger).toHaveFocus();
  });

  it('selects with ArrowDown and Enter', async () => {
    const { user, trigger, onChange } = setup();

    await user.click(trigger);
    await screen.findByRole('listbox');
    await user.keyboard('{ArrowDown}{Enter}');

    expect(onChange).toHaveBeenCalledWith('doha');
  });

  it('closes on an outside click without choosing', async () => {
    const { user, trigger, onChange } = setup();

    await user.click(trigger);
    await screen.findByRole('listbox');
    await user.click(screen.getByText('Outside'));

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
    expect(onChange).not.toHaveBeenCalled();
  });

  it('shows a value that is not among the options instead of the placeholder', () => {
    setup('removed-city');

    expect(screen.getByRole('combobox', { name: 'City' })).toHaveTextContent('removed-city');
    expect(screen.queryByText('All Regions')).not.toBeInTheDocument();
  });

  it('shows the placeholder while nothing is chosen', () => {
    setup(null);

    expect(screen.getByRole('combobox', { name: 'City' })).toHaveTextContent('All Regions');
  });

  it('closes on Escape inside the filter sheet without closing the sheet', async () => {
    const user = userEvent.setup();
    const doha: Location = { id: 'doha', parent_id: null, slug: 'doha', name: { ar: 'الدوحة', en: 'Doha' }, type: 'city', lat: null, lng: null, children: [] };
    render(<FilterSheet groups={['location']} values={EMPTY_FILTERS} locations={[doha]} onApply={vi.fn()} onReset={vi.fn()} />);

    await user.click(screen.getByRole('button', { name: 'Filter' }));
    const dialog = await screen.findByRole('dialog', { name: 'Filter' });
    const field = screen.getByRole('combobox', { name: 'City / Region' });
    await user.click(field);
    await screen.findByRole('listbox');
    await user.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
    expect(dialog).toBeInTheDocument();
    expect(screen.getByRole('dialog', { name: 'Filter' })).toBeInTheDocument();
  });
});
