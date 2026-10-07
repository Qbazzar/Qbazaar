import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/api/wallet', () => ({ listBankAccounts: vi.fn(), addBankAccount: vi.fn(), deleteBankAccount: vi.fn() }));

import type { BankAccount } from '@/lib/api/commerce-types';
import type { User } from '@/lib/api/types';
import { addBankAccount, deleteBankAccount, listBankAccounts } from '@/lib/api/wallet';
import { setClientLocale } from '@/lib/i18n/locale';
import { renderWithClient } from '@/components/orders/test-utils';
import { useAuthStore } from '@/store/auth';

import { BankAccountsView } from './BankAccountsView';

const account: BankAccount = {
  id: 'bank-1',
  holder_name: 'Mohammed Al Kuwari',
  iban_masked: 'QA** **** DEFG',
  bank_name: 'QNB',
  is_default: true,
  created_at: '2026-10-01T10:00:00Z',
};

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'seller-1' } as User, accessToken: 'AT', isHydrated: true });
});

describe('BankAccountsView', () => {
  it('checks the IBAN before saving, then sends it normalised as the first, default account', async () => {
    vi.mocked(listBankAccounts).mockResolvedValue([]);
    vi.mocked(addBankAccount).mockResolvedValue(account);
    renderWithClient(<BankAccountsView />);

    expect(await screen.findByText('No payout accounts yet.')).toBeInTheDocument();
    await userEvent.click(screen.getByLabelText(/Account holder name/));
    await userEvent.paste('Mohammed Al Kuwari');
    await userEvent.click(screen.getByLabelText(/^IBAN/));
    await userEvent.paste('QA59 DOHB 0000 1234 5678 90AB CDEF G');
    await userEvent.click(screen.getByRole('button', { name: 'Save account' }));
    expect(await screen.findByText("This IBAN doesn't look right. Check it against your bank details.")).toBeInTheDocument();
    expect(addBankAccount).not.toHaveBeenCalled();

    await userEvent.clear(screen.getByLabelText(/^IBAN/));
    await userEvent.paste('qa58 dohb 0000 1234 5678 90ab cdef g');
    await userEvent.click(screen.getByRole('button', { name: 'Save account' }));

    await waitFor(() =>
      expect(addBankAccount).toHaveBeenCalledWith({
        holder_name: 'Mohammed Al Kuwari',
        iban: 'QA58DOHB00001234567890ABCDEFG',
        bank_name: null,
        is_default: true,
      }),
    );
  });

  it('lists accounts masked and removes one after confirming', async () => {
    vi.mocked(listBankAccounts).mockResolvedValue([account]);
    vi.mocked(deleteBankAccount).mockResolvedValue(undefined);
    renderWithClient(<BankAccountsView />);

    expect(await screen.findByText('QA** **** DEFG')).toBeInTheDocument();
    expect(screen.getByText('Default')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Remove the account ending DEFG' }));
    const dialog = await screen.findByRole('dialog', { name: 'Remove this account?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Remove account' }));

    await waitFor(() => expect(deleteBankAccount).toHaveBeenCalledWith('bank-1'));
  });
});
