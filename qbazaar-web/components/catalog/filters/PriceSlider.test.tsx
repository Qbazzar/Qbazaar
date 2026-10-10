import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { PriceSlider } from './PriceSlider';

describe('PriceSlider', () => {
  beforeEach(() => setClientLocale('en'));

  it('names both handles and shows the open range under them', () => {
    render(<PriceSlider min={null} max={null} onChange={vi.fn()} />);

    expect(screen.getByRole('slider', { name: 'Minimum price (QAR)' })).toHaveAttribute('aria-valuetext', 'QAR 0');
    expect(screen.getByRole('slider', { name: 'Maximum price (QAR)' })).toHaveAttribute('aria-valuetext', 'QAR 5,000,000+');
    expect(screen.getByText('QAR 0')).toBeInTheDocument();
    expect(screen.getByText('QAR 5,000,000+')).toBeInTheDocument();
  });

  it('moves one handle by a stop from the keyboard and leaves the other price as typed', async () => {
    const onChange = vi.fn();
    render(<PriceSlider min={1_234} max={null} onChange={onChange} />);

    screen.getByRole('slider', { name: 'Maximum price (QAR)' }).focus();
    await userEvent.setup().keyboard('{ArrowLeft}');

    expect(onChange).toHaveBeenLastCalledWith({ priceMin: 1_234, priceMax: 2_500_000 });
  });

  it('opens the minimum again at the start of the scale', async () => {
    const onChange = vi.fn();
    render(<PriceSlider min={50} max={1_000} onChange={onChange} />);

    screen.getByRole('slider', { name: 'Minimum price (QAR)' }).focus();
    await userEvent.setup().keyboard('{Home}');

    expect(onChange).toHaveBeenLastCalledWith({ priceMin: null, priceMax: 1_000 });
  });
});
