import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/account', () => ({ revokeSession: vi.fn() }));

import { revokeSession } from '@/lib/api/account';
import { t } from '@/lib/i18n/messages';
import type { UserSession } from '@/lib/api/types';
import { SessionsList } from './SessionsList';

const mockRevoke = vi.mocked(revokeSession);

const sessions: UserSession[] = [
  {
    id: '15',
    device_label: 'Chrome on Windows',
    ip_address: '10.0.0.1',
    user_agent: null,
    last_used_at: new Date().toISOString(),
    created_at: new Date().toISOString(),
    is_current: true,
  },
  {
    id: '12',
    device_label: 'unknown',
    ip_address: '10.0.0.2',
    user_agent: null,
    last_used_at: new Date().toISOString(),
    created_at: new Date().toISOString(),
    is_current: false,
  },
];

function renderList() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <SessionsList sessions={sessions} />
    </QueryClientProvider>,
  );
}

beforeEach(() => vi.clearAllMocks());

describe('SessionsList', () => {
  it('marks this device and only offers sign-out for the others', () => {
    renderList();

    expect(screen.getByText(t('account.sessions.current_badge'))).toBeInTheDocument();
    expect(screen.getAllByText(t('account.sessions.unknown_device'))[0]).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: new RegExp(t('account.sessions.revoke')) })).toHaveLength(1);
  });

  it('asks for confirmation before signing a device out', async () => {
    mockRevoke.mockResolvedValue(undefined as never);
    renderList();

    // The row button names the device it signs out.
    fireEvent.click(
      screen.getByRole('button', { name: `${t('account.sessions.revoke')} ${t('account.sessions.unknown_device')}` }),
    );
    expect(mockRevoke).not.toHaveBeenCalled();

    const dialog = await screen.findByRole('dialog');
    expect(dialog).toHaveAccessibleName(t('account.sessions.confirm_title'));
    fireEvent.click(within(dialog).getByRole('button', { name: t('account.sessions.revoke') }));

    await waitFor(() => expect(mockRevoke).toHaveBeenCalledWith('12'));
  });
});
