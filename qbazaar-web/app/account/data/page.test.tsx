import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ replace }) }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/hooks/useAuth', () => ({ useAuth: () => ({ logout: vi.fn() }) }));
vi.mock('@/lib/api/account', () => ({ deactivateAccount: vi.fn(), requestAccountDeletion: vi.fn() }));

import { toast } from 'sonner';

import { deactivateAccount, requestAccountDeletion } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

import AccountDataPage from './page';

function apiError(code: string, details?: Record<string, unknown>, status = 422) {
  return new ApiClientError({
    status,
    code,
    messageKey: code,
    message: 'raw english message',
    details: details as never,
  });
}

async function openDeleteDialog() {
  render(<AccountDataPage />);
  await userEvent.click(screen.getByRole('checkbox'));
  await userEvent.click(screen.getByRole('button', { name: t('account.data.delete.submit') }));
  return screen.getByRole('dialog');
}

async function submitDelete(password = 'secret-pass') {
  const dialog = await openDeleteDialog();
  await userEvent.type(within(dialog).getByLabelText(new RegExp('^' + t('account.data.delete.password_label'))), password);
  await userEvent.click(within(dialog).getByRole('button', { name: t('account.data.delete.confirm') }));
  return dialog;
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
});

describe('account deletion refusals', () => {
  it.each([
    ['ACCOUNT_003', { amount: '12.50', currency: 'QAR' }, 'You still owe 12.50 QAR', '/account/wallet'],
    ['ACCOUNT_004', { order_id: '01J9Z3' }, 'order in progress', '/account/orders/01J9Z3'],
    ['ACCOUNT_005', { amount: '40.00', currency: 'QAR' }, 'wallet still holds 40.00 QAR', '/account/wallet'],
    ['ACCOUNT_006', { withdrawal_id: 'w1' }, 'waiting for review', '/account/wallet'],
  ])('%s is explained in words with a link to fix it', async (code, details, text, href) => {
    vi.mocked(requestAccountDeletion).mockRejectedValue(apiError(code, details));

    const dialog = await submitDelete();

    const alert = await within(dialog).findByRole('alert');
    expect(alert).toHaveTextContent(text);
    expect(alert).not.toHaveTextContent('raw english message');
    expect(within(alert).getByRole('link')).toHaveAttribute('href', href);
    expect(replace).not.toHaveBeenCalled();
  });

  it('has Arabic text for every refusal', () => {
    setClientLocale('ar');
    for (const code of ['ACCOUNT_003', 'ACCOUNT_004', 'ACCOUNT_005', 'ACCOUNT_006']) {
      expect(t(`account.errors.${code}`)).not.toBe(`account.errors.${code}`);
    }
    setClientLocale('en');
  });
});

describe('wrong password', () => {
  // The API answers USER_005 for a wrong password on both requests.
  it('on delete, USER_005 marks the password field', async () => {
    vi.mocked(requestAccountDeletion).mockRejectedValue(apiError('USER_005'));

    const dialog = await submitDelete();

    expect(await within(dialog).findByText(t('account.errors.USER_005'))).toBeInTheDocument();
    expect(toast.error).toHaveBeenCalledWith(t('account.errors.USER_005'));
  });

  it('on deactivate, USER_005 marks the password field', async () => {
    vi.mocked(deactivateAccount).mockRejectedValue(apiError('USER_005'));
    render(<AccountDataPage />);

    await userEvent.click(screen.getByRole('button', { name: t('account.data.deactivate.submit') }));
    const dialog = screen.getByRole('dialog');
    await userEvent.type(within(dialog).getByLabelText(new RegExp('^' + t('account.data.deactivate.password_label'))), 'nope');
    await userEvent.click(within(dialog).getByRole('button', { name: t('account.data.deactivate.confirm') }));

    await waitFor(() => expect(within(dialog).getByText(t('account.errors.USER_005'))).toBeInTheDocument());
  });
});
