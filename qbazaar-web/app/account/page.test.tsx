import { render, screen, waitFor, within } from '@testing-library/react';
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
vi.mock('@/lib/api/account', () => ({ getAccountSummary: vi.fn() }));

import { getAccountSummary } from '@/lib/api/account';
import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { AccountSummary } from '@/lib/api/types';

import AccountHubPage from './page';

/** The `AccountSummary` schema of the contract (GET /account/summary). */
const summary: AccountSummary = {
  my_ads: 12,
  drafts: 2,
  ads_by_status: { draft: 2, pending: 1, active: 9, sold: 1, expired: 1, rejected: 0, blocked: 0 },
  conversations: 5,
  unread_messages: 3,
  unread_notifications: 4,
  favorites: 1525,
  saved_searches: 6,
};

function renderHub() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <AccountHubPage />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  setClientLocale('en');
  vi.mocked(getAccountSummary).mockResolvedValue(summary);
});
afterEach(() => setClientLocale('ar'));

// The tiles list is the one marked busy while the summary refetches; the phone hub rows are not.
const inTiles = (el: HTMLElement) => el.closest('ul[aria-busy]') !== null;

function tileValue(labelKey: string): string | null | undefined {
  const label = screen.getAllByText(t(labelKey)).find(inTiles);
  return label?.nextElementSibling?.textContent;
}

function hubRow(labelKey: string): HTMLElement {
  const label = screen.getAllByText(t(labelKey)).find((el) => !inTiles(el));
  return label?.closest('a') as HTMLElement;
}

describe('AccountHubPage', () => {
  it('shows the contract summary counters on the overview tiles', async () => {
    renderHub();

    await waitFor(() => expect(tileValue('account.dashboard.stats.my_ads')).toBe('12'));
    expect(tileValue('account.dashboard.stats.drafts')).toBe('2');
    expect(tileValue('account.dashboard.stats.unread_messages')).toBe('3');
    expect(tileValue('account.dashboard.stats.unread_notifications')).toBe('4');
    expect(tileValue('account.dashboard.stats.favorites')).toBe('1,525');
    expect(tileValue('account.dashboard.stats.saved_searches')).toBe('6');
  });

  it('badges the phone hub rows with the same counters', async () => {
    renderHub();

    expect(await within(hubRow('account.nav.messages')).findByText('3')).toBeInTheDocument();
    expect(within(hubRow('account.nav.notifications')).getByText('4')).toBeInTheDocument();
    expect(within(hubRow('account.nav.favorites')).getByText('99+')).toBeInTheDocument();
  });
});
