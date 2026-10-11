import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { AccountMenu } from './AccountMenu';

vi.mock('@/components/account/useSignOut', () => ({ useSignOut: () => ({ signOut: vi.fn(), pending: false }) }));

const avatar = () => screen.getByRole('link', { name: 'SA, Account' });
const panel = () => document.querySelector('nav');
const rowLinks = () => [...(panel()?.querySelectorAll<HTMLElement>('a, button') ?? [])];

function setup() {
  render(
    <>
      <button type="button">before</button>
      <AccountMenu name="Sara Ali" email="sara@example.com" />
      <button type="button">after</button>
    </>,
  );
}

describe('AccountMenu', () => {
  beforeEach(() => setClientLocale('en'));

  it('opens under a resting mouse, with its rows in the tab order', async () => {
    const user = userEvent.setup();
    setup();
    await user.hover(avatar());

    expect(panel()).toBeInTheDocument();
    expect(rowLinks().every((row) => row.tabIndex !== -1)).toBe(true);
    expect(avatar()).toHaveAttribute('href', '/account');
  });

  it('opens on a first touch tap without navigating, and the second tap navigates', () => {
    setup();
    fireEvent.pointerDown(avatar(), { pointerType: 'touch' });
    expect(fireEvent.click(avatar())).toBe(false);
    expect(panel()).toBeInTheDocument();
    expect(rowLinks().map((row) => row.getAttribute('href'))).toContain('/account/saved-searches');

    fireEvent.pointerDown(avatar(), { pointerType: 'touch' });
    expect(fireEvent.click(avatar())).toBe(true);
  });

  it('keeps the rows out of the tab order while only the focus opened it', async () => {
    const user = userEvent.setup();
    setup();
    await user.tab();
    await user.tab();

    expect(avatar()).toHaveFocus();
    expect(panel()).toBeInTheDocument();
    expect(rowLinks().every((row) => row.tabIndex === -1)).toBe(true);

    await user.tab();
    expect(screen.getByRole('button', { name: 'after' })).toHaveFocus();
    expect(panel()).toBeNull();
  });

  it('ArrowDown and Enter make the rows reachable and move into them', async () => {
    const user = userEvent.setup();
    setup();
    avatar().focus();
    await user.keyboard('{ArrowDown}');
    expect(rowLinks()[0]).toHaveFocus();
    expect(rowLinks().every((row) => row.tabIndex !== -1)).toBe(true);

    await user.keyboard('{Escape}');
    expect(panel()).toBeNull();
    expect(avatar()).toHaveFocus();

    await user.keyboard('{Enter}');
    expect(panel()).toBeInTheDocument();
    expect(rowLinks()[0]).toHaveFocus();
  });

  it('closes with Escape and gives the focus back to the avatar', async () => {
    const user = userEvent.setup();
    setup();
    await user.hover(avatar());
    rowLinks()[1].focus();
    await user.keyboard('{Escape}');

    expect(panel()).toBeNull();
    expect(avatar()).toHaveFocus();
  });
});
