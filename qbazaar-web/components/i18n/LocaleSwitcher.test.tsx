import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

const switchLocale = vi.hoisted(() => vi.fn());
vi.mock('./switch-locale', () => ({ switchLocale }));

import { LocaleSwitcher } from './LocaleSwitcher';

describe('LocaleSwitcher', () => {
  it('names itself after the code it shows, in the language it offers', async () => {
    setClientLocale('ar');
    render(<LocaleSwitcher />);
    const button = screen.getByRole('button', { name: 'EN, Switch to English' });

    expect(button).toHaveTextContent('EN');
    expect(button).toHaveAttribute('lang', 'en');
    await userEvent.click(button);
    expect(switchLocale).toHaveBeenCalledWith('en');
  });

  it('offers Arabic on English pages', () => {
    setClientLocale('en');
    render(<LocaleSwitcher />);

    expect(screen.getByRole('button', { name: 'ع، التبديل إلى العربية' })).toHaveAttribute('lang', 'ar');
  });
});
