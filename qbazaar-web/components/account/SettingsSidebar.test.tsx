import type { AnchorHTMLAttributes, MouseEvent } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The route is the `pathname` prop here, so a pressed link only reports the press and never navigates.
vi.mock('next/link', () => ({
  default: ({ href, onClick, ...props }: AnchorHTMLAttributes<HTMLAnchorElement> & { href: string }) => (
    <a
      href={href}
      onClick={(event: MouseEvent<HTMLAnchorElement>) => {
        onClick?.(event);
        event.preventDefault();
      }}
      {...props}
    />
  ),
}));
vi.mock('./useSignOut', () => ({ useSignOut: () => ({ signOut: vi.fn(), pending: false }) }));

import { setClientLocale } from '@/lib/i18n/locale';

import { SettingsSidebar } from './SettingsSidebar';

const row = (name: string) => screen.getByRole('link', { name });
const currentRows = () => screen.getAllByRole('link').filter((link) => link.getAttribute('aria-current') === 'page');

beforeEach(() => setClientLocale('en'));
afterEach(() => setClientLocale('ar'));

describe('SettingsSidebar', () => {
  it('lights a pressed row at once, before its page has loaded', async () => {
    render(<SettingsSidebar pathname="/account/profile" />);
    expect(currentRows()).toEqual([row('Profile Settings')]);

    await userEvent.click(row('Wallet'));
    expect(currentRows()).toEqual([row('Wallet')]);
  });

  it('hands the row back to the route once it changes, also when the user comes back to the page of the press', async () => {
    const { rerender } = render(<SettingsSidebar pathname="/account/profile" />);
    await userEvent.click(row('Wallet'));

    rerender(<SettingsSidebar pathname="/account/wallet" />);
    expect(currentRows()).toEqual([row('Wallet')]);

    // The browser's Back button: the press is spent, so the page's own row is lit again.
    rerender(<SettingsSidebar pathname="/account/profile" />);
    expect(currentRows()).toEqual([row('Profile Settings')]);

    rerender(<SettingsSidebar pathname="/account/security" />);
    expect(currentRows()).toEqual([row('Account Settings')]);
  });

  it('leaves the lit row alone for a link row and for a click that opens another tab', () => {
    render(<SettingsSidebar pathname="/account/profile" />);

    fireEvent.click(row('Promotions'));
    fireEvent.click(row('Wallet'), { ctrlKey: true });
    fireEvent.click(row('Account Settings'), { button: 1 });

    expect(currentRows()).toEqual([row('Profile Settings')]);
  });
});
