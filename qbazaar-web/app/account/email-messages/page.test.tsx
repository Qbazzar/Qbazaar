import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/account', () => ({
  getEmailPreferences: vi.fn(),
  updateEmailPreferences: vi.fn(),
  getAccountProfile: vi.fn(() => new Promise(() => {})),
}));

import { toast } from 'sonner';

import { renderWithClient } from '@/components/orders/test-utils';
import { getEmailPreferences, updateEmailPreferences } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

import AccountEmailMessagesPage from './page';

const preferences = {
  user_messages: true,
  offers: true,
  listing_updates: true,
  saved_search_alerts: true,
  newsletters: false,
};

const newsletters = () => screen.findByRole('switch', { name: new RegExp(t('account.email_messages.topics.newsletters.title')) });

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  vi.mocked(getEmailPreferences).mockResolvedValue(preferences as never);
});

describe('email message switches', () => {
  it('flips at once and keeps the saved value', async () => {
    let finish: (value: unknown) => void = () => {};
    vi.mocked(updateEmailPreferences).mockReturnValue(new Promise((resolve) => (finish = resolve)) as never);
    renderWithClient(<AccountEmailMessagesPage />);

    const toggle = await newsletters();
    await userEvent.click(toggle);

    await waitFor(() => expect(toggle).toBeChecked());
    expect(vi.mocked(updateEmailPreferences).mock.calls[0][0]).toEqual({ newsletters: true });
    finish({ ...preferences, newsletters: true });
    await waitFor(() => expect(toggle).toBeChecked());
  });

  it('rolls back and explains when the request fails', async () => {
    vi.mocked(updateEmailPreferences).mockRejectedValue(
      new ApiClientError({ status: 500, code: 'SERVER_ERROR', messageKey: 'x', message: 'Server exploded' }),
    );
    renderWithClient(<AccountEmailMessagesPage />);

    const toggle = await newsletters();
    expect(toggle).not.toBeChecked();
    await userEvent.click(toggle);

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Server exploded'));
    await waitFor(() => expect(toggle).not.toBeChecked());
  });
});
