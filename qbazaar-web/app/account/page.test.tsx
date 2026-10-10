import { render, screen, within } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn() }) }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/hooks/useAuth', () => ({
  useAuth: () => ({
    user: {
      id: '01J0USER',
      full_name: 'Farah Alzinati',
      email: 'farah@example.qa',
      avatar_url: null,
      account_type: 'private',
      created_at: '2016-01-08T10:00:00Z',
    },
    logout: vi.fn(),
  }),
}));
vi.mock('@/lib/api/account', () => ({
  getAccountProfile: vi.fn(() => new Promise(() => {})),
  listAddresses: vi.fn(() => new Promise(() => {})),
}));

import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

import AccountHubPage from './page';

function renderHub() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <AccountHubPage />
    </QueryClientProvider>,
  );
}

beforeEach(() => setClientLocale('en'));
afterEach(() => setClientLocale('ar'));

describe('AccountHubPage', () => {
  it('lists who is signed in, then the settings sections of acct.js on the phone hub', () => {
    renderHub();
    const hub = screen.getByRole('navigation', { name: t('account.nav.settings') });

    expect(within(hub).getByText('Farah Alzinati')).toBeInTheDocument();
    expect(within(hub).getByText('farah@example.qa')).toBeInTheDocument();
    expect(within(hub).getAllByRole('link').map((link) => [link.textContent?.replace('›', '').trim(), link.getAttribute('href')])).toEqual([
      ['Profile Settings', '/account/profile'],
      ['Wallet', '/account/wallet'],
      ['Account Settings', '/account/security'],
      ['Data Protection', '/account/privacy'],
      ['Email Message', '/account/email-messages'],
      ['Marketplace Info', '/account/marketplace'],
      ['Delete Account', '/account/data'],
    ]);
  });

  it('leaves "Log Out" to the menu drawer, as the design hub does', () => {
    renderHub();

    expect(screen.queryByRole('button', { name: t('account.nav.log_out') })).not.toBeInTheDocument();
  });
});
