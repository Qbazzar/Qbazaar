import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/api/wallet', () => ({
  getWallet: vi.fn(),
  listBankAccounts: vi.fn(),
  listWithdrawals: vi.fn(),
  createWithdrawal: vi.fn(),
}));

import { ApiClientError } from '@/lib/api/auth';
import type { BankAccount, Withdrawal } from '@/lib/api/commerce-types';
import type { User } from '@/lib/api/types';
import { createWithdrawal, getWallet, listBankAccounts, listWithdrawals } from '@/lib/api/wallet';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildWallet } from '@/lib/orders/test-fixtures';
import { renderWithClient } from '@/components/orders/test-utils';
import { useAuthStore } from '@/store/auth';

import { WithdrawalView } from './WithdrawalView';

const account: BankAccount = {
  id: 'bank-1',
  holder_name: 'Mohammed Al Kuwari',
  iban_masked: 'QA** **** DEFG',
  bank_name: 'QNB',
  is_default: true,
  created_at: '2026-10-01T10:00:00Z',
};

const empty = { success: true as const, data: [] as Withdrawal[], meta: { per_page: 20, has_more: false, next_cursor: null } };

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'seller-1' } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(listWithdrawals).mockResolvedValue(empty);
});

describe('WithdrawalView', () => {
  it('withdraws up to the withdrawable amount into the default account', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '300.00', withdrawable_balance: '200.00', commission_debt: '100.00' }));
    vi.mocked(listBankAccounts).mockResolvedValue([account]);
    vi.mocked(createWithdrawal).mockResolvedValue({} as Withdrawal);
    renderWithClient(<WithdrawalView />);

    expect(await screen.findByText('You can withdraw QAR 200.00')).toBeInTheDocument();
    expect(screen.getByText('The commission you owe (QAR 100.00) is paid from your wallet first.')).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /Mohammed Al Kuwari/ })).toBeChecked();

    await userEvent.type(screen.getByLabelText(/Amount/), '250');
    await userEvent.click(screen.getByRole('button', { name: 'Request withdrawal' }));
    expect(await screen.findByText('The amount can\'t be more than QAR 200.00.')).toBeInTheDocument();

    await userEvent.clear(screen.getByLabelText(/Amount/));
    await userEvent.type(screen.getByLabelText(/Amount/), '150.5');
    await userEvent.click(screen.getByRole('button', { name: 'Request withdrawal' }));

    await waitFor(() =>
      expect(createWithdrawal).toHaveBeenCalledWith({ amount: '150.50', bank_account_id: 'bank-1' }, expect.any(String)),
    );
  });

  it('shows the cap the API reports with WALLET_003', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '300.00', withdrawable_balance: '200.00' }));
    vi.mocked(listBankAccounts).mockResolvedValue([account]);
    vi.mocked(createWithdrawal).mockRejectedValue(
      new ApiClientError({ status: 422, code: 'WALLET_003', messageKey: 'x', message: 'Too much', details: { withdrawable: ['120.00'] } }),
    );
    renderWithClient(<WithdrawalView />);

    await userEvent.type(await screen.findByLabelText(/Amount/), '180');
    await userEvent.click(screen.getByRole('button', { name: 'Request withdrawal' }));

    expect(await screen.findByText('You can withdraw at most QAR 120.00.')).toBeInTheDocument();
  });

  it('asks for a payout account first, and says when there is nothing to withdraw', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '50.00', withdrawable_balance: '50.00', commission_debt: '0.00' }));
    vi.mocked(listBankAccounts).mockResolvedValue([]);
    const { unmount } = renderWithClient(<WithdrawalView />);
    expect(await screen.findByRole('link', { name: 'Add a payout account' })).toHaveAttribute('href', '/account/wallet/bank-accounts');
    unmount();

    vi.mocked(getWallet).mockResolvedValue(buildWallet());
    vi.mocked(listBankAccounts).mockResolvedValue([account]);
    renderWithClient(<WithdrawalView />);
    expect(await screen.findByText('You have nothing to withdraw right now.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Request withdrawal' })).not.toBeInTheDocument();
  });
});
