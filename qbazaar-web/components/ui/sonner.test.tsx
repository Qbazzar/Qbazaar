import { act, render, screen } from '@testing-library/react';
import { toast } from 'sonner';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { Toaster } from './sonner';

async function toastElement(text: string): Promise<HTMLElement> {
  const element = (await screen.findByText(text)).closest<HTMLElement>('[data-sonner-toast]');
  if (!element) throw new Error(`no toast holds "${text}"`);
  return element;
}

describe('Toaster', () => {
  beforeEach(() => setClientLocale('en'));
  afterEach(() => act(() => void toast.dismiss()));

  it('shows a confirmation as the design banner: mint, no border, no close button', async () => {
    render(<Toaster />);
    act(() => void toast.success('Report submitted. Thank you.'));
    const banner = await toastElement('Report submitted. Thank you.');

    expect(banner).toHaveAttribute('data-type', 'success');
    expect(banner).toHaveAttribute('data-rich-colors', 'true');
    expect(banner).toHaveClass('border-0!', 'shadow-qb-toast!', 'text-qb-body-sm!', 'qb-tablet:text-qb-h5!', 'qb-tablet:w-fit!');
    expect(banner.querySelector('[data-close-button]')).toBeNull();
  });

  it('sits 92 px from the top (84 on phones), up to 574 px wide (92vw on phones)', async () => {
    render(<Toaster />);
    act(() => void toast.error('Something went wrong'));
    const toaster = (await toastElement('Something went wrong')).closest<HTMLElement>('[data-sonner-toaster]');

    expect(toaster).toHaveAttribute('data-y-position', 'top');
    expect(toaster).toHaveAttribute('data-x-position', 'center');
    expect(toaster?.style.getPropertyValue('--width')).toBe('min(574px, 92vw)');
    expect(toaster?.style.getPropertyValue('--offset-top')).toBe('92px');
    expect(toaster?.style.getPropertyValue('--mobile-offset-top')).toBe('84px');
    expect(toaster?.style.getPropertyValue('--mobile-offset-left')).toBe('4vw');
    expect(toaster?.style.getPropertyValue('--success-bg')).toBe('var(--color-qb-toast-mint)');
  });

  it('leaves the box of a custom toast to its own markup', async () => {
    render(<Toaster />);
    act(() => void toast.custom(() => <div>Custom banner</div>));

    expect(await toastElement('Custom banner')).not.toHaveClass('shadow-qb-toast!');
  });
});
