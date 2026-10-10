import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => '/account/wallet',
}));
vi.mock('@/lib/api/wallet', () => ({ getWallet: vi.fn(), listWalletEntries: vi.fn() }));

import { ACCOUNT_HUB_ITEM, ACTIVITY_NAV } from '@/components/account/account-nav';
import { SettingsShell } from '@/components/account/SettingsShell';
import { renderWithClient } from '@/components/orders/test-utils';
import type { User } from '@/lib/api/types';
import { getWallet, listWalletEntries } from '@/lib/api/wallet';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildWallet } from '@/lib/orders/test-fixtures';
import { useAuthStore } from '@/store/auth';

import { WalletView } from './WalletView';

const WITHDRAW_HREF = '/account/wallet/withdrawals';

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'seller-1' } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(listWalletEntries).mockResolvedValue({ success: true, data: [], meta: { per_page: 20, has_more: false, next_cursor: null } });
});

describe('WalletView', () => {
  it('links to paying the commission while some is owed', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ commission_debt: '277.50' }));
    renderWithClient(<WalletView />);

    const link = await screen.findByRole('link', { name: 'Pay commission' });
    expect(link).toHaveAttribute('href', '/account/wallet/settlements');
  });

  it('hides the pay link when no commission is owed', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ commission_debt: '0.00' }));
    renderWithClient(<WalletView />);

    expect(await screen.findByText('Commission owed')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Pay commission' })).not.toBeInTheDocument();
  });

  it('has one Withdraw link for each breakpoint: the header on desktop, the history card below it', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet());
    renderWithClient(<WalletView />);
    await screen.findByText('Commission owed');

    const links = screen.getAllByRole('link', { name: /Withdraw/ }).filter((link) => link.getAttribute('href') === WITHDRAW_HREF);
    expect(links).toHaveLength(2);
    expect(links.filter((link) => link.className.includes('qb-desktop:inline-flex'))).toHaveLength(1);
    expect(links.filter((link) => link.className.includes('qb-desktop:hidden'))).toHaveLength(1);
  });

  it('greys the tile labels and leaves the hints their own colour', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet());
    renderWithClient(<WalletView />);

    const label = await screen.findByText('Commission owed');
    expect(label).toHaveClass('text-(--color-qb-ink-meta)', 'max-qb-tablet:text-qb-breadcrumb');
    expect(label.parentElement?.className).not.toContain('--color-qb-ink-subtle');
    expect(screen.getByText('Your balance minus the commission you owe.')).toHaveClass('text-qb-ink-subtle');
  });

  it('keeps Orders and Promotions one step away: the wallet sidebar leads to the hub that lists them', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet());
    renderWithClient(
      <SettingsShell>
        <WalletView />
      </SettingsShell>,
    );
    await screen.findByText('Commission owed');

    const sidebar = screen.getByRole('navigation', { name: 'Settings' });
    expect(sidebar.querySelector(`a[href="${ACCOUNT_HUB_ITEM.href}"]`)).not.toBeNull();
    const reachable = ACTIVITY_NAV.map((item) => item.href);
    expect(reachable).toContain('/account/orders');
    expect(reachable).toContain('/account/promotions');
  });
});
