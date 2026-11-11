import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { NuqsTestingAdapter } from 'nuqs/adapters/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/notifications', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/notifications')>()),
  listNotifications: vi.fn(),
}));
vi.mock('@/components/notifications/EnablePushButton', () => ({ EnablePushButton: () => null }));
vi.mock('@/components/notifications/NotificationRow', () => ({
  NotificationRow: ({ notification }: { notification: Notification }) => <p>{notification.title}</p>,
}));

import { listNotifications } from '@/lib/api/notifications';
import type { Notification, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

import { NotificationsClient } from './NotificationsClient';

function notification(id: string, title: string): Notification {
  return { id, type: 'ad.price_changed', category: 'ad.price_changed', title, body: '', cta_url: null, icon: null, read_at: null, created_at: '2026-11-01T10:00:00Z' };
}

function renderNotifications() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <NuqsTestingAdapter>
      <QueryClientProvider client={client}>
        <NotificationsClient />
      </QueryClientProvider>
    </NuqsTestingAdapter>,
  );
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'user-1' } as User, accessToken: 'AT', isHydrated: true });
});
afterEach(() => setClientLocale('ar'));

describe('NotificationsClient', () => {
  it('toggles the "Select All" box from its caption too, as the label of notifications.html does', async () => {
    vi.mocked(listNotifications).mockResolvedValue({
      data: [notification('n-1', 'Price dropped'), notification('n-2', 'Saved search match')],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
      links: { first: null, last: null, prev: null, next: null },
    });
    const user = userEvent.setup();
    renderNotifications();

    const selectAll = await screen.findByRole('checkbox', { name: 'Select All' });
    const rows = () => screen.getAllByRole('checkbox', { name: /^Select notification:/ });
    expect(selectAll).toHaveAttribute('aria-checked', 'false');

    // The caption is hidden from assistive tech (the box carries the name), so it is found by its text.
    await user.click(screen.getByText('Select All'));
    expect(selectAll).toHaveAttribute('aria-checked', 'true');
    for (const row of rows()) expect(row).toHaveAttribute('aria-checked', 'true');

    await user.click(screen.getByText('Select All'));
    expect(selectAll).toHaveAttribute('aria-checked', 'false');
    for (const row of rows()) expect(row).toHaveAttribute('aria-checked', 'false');
  });
});
