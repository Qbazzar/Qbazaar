import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { ResultsFocusProvider } from '../results-focus';
import { ResultsHeading } from '../ResultsHeading';

import type { ActiveFilter } from './active-filters';
import { ActiveFilterChips } from './ActiveFilterChips';
import { EMPTY_FILTERS } from './filter-values';

const chip = (key: string, label: string): ActiveFilter => ({ key, label, without: EMPTY_FILTERS });

function Page({ chips, onRemove }: { chips: ActiveFilter[]; onRemove: () => void }) {
  return (
    <ResultsFocusProvider>
      <ActiveFilterChips chips={chips} onRemove={onRemove} focusNextAfterRemove />
      <ResultsHeading loading={false} total={4} />
    </ResultsFocusProvider>
  );
}

describe('ActiveFilterChips', () => {
  beforeEach(() => setClientLocale('en'));

  it('moves the focus to the next chip, then to the results after the last one', async () => {
    const user = userEvent.setup();
    const onRemove = vi.fn();
    const [doha, price, used] = [chip('location', 'Doha'), chip('price', 'From QAR 100'), chip('condition', 'Used')];
    const { rerender } = render(<Page chips={[doha, price, used]} onRemove={onRemove} />);

    await user.click(screen.getByRole('button', { name: 'Remove From QAR 100' }));
    rerender(<Page chips={[doha, used]} onRemove={onRemove} />);
    expect(screen.getByRole('button', { name: 'Remove Used' })).toHaveFocus();

    await user.click(screen.getByRole('button', { name: 'Remove Used' }));
    rerender(<Page chips={[doha]} onRemove={onRemove} />);
    expect(screen.getByRole('button', { name: 'Remove Doha' })).toHaveFocus();

    await user.click(screen.getByRole('button', { name: 'Remove Doha' }));
    rerender(<Page chips={[]} onRemove={onRemove} />);
    expect(screen.getByRole('heading', { name: 'Results' })).toHaveFocus();
    expect(onRemove).toHaveBeenCalledTimes(3);
  });
});
