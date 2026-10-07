import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/api/wallet', () => ({ getWallet: vi.fn(), listSettlements: vi.fn(), createSettlement: vi.fn() }));

import type { Settlement } from '@/lib/api/commerce-types';
import type { User } from '@/lib/api/types';
import { createSettlement, getWallet, listSettlements } from '@/lib/api/wallet';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildWallet } from '@/lib/orders/test-fixtures';
import { renderWithClient } from '@/components/orders/test-utils';
import { useAuthStore } from '@/store/auth';

import { SettlementView } from './SettlementView';

function page(data: Settlement[] = []) {
  return { success: true as const, data, meta: { per_page: 20, has_more: false, next_cursor: null } };
}

const pendingTransfer: Settlement = {
  id: 'set-1',
  method: 'bank_transfer',
  status: 'pending',
  currency: 'QAR',
  amount: '100.00',
  bank_reference: 'TRF-1',
  rejection_reason: null,
  created_at: '2026-10-05T10:00:00Z',
  reviewed_at: null,
};

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'seller-1' } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(createSettlement).mockResolvedValue({} as Settlement);
});

describe('SettlementView', () => {
  it('needs a reference and a receipt image for a bank transfer', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet());
    vi.mocked(listSettlements).mockResolvedValue(page());
    renderWithClient(<SettlementView />);

    expect(await screen.findByText('You owe QAR 277.50')).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /From my wallet/ })).toBeDisabled();
    expect(screen.getByLabelText(/Amount/)).toHaveValue('277.50');

    await userEvent.click(screen.getByRole('button', { name: 'Submit payment' }));
    expect(await screen.findByText('This field is required.')).toBeInTheDocument();
    expect(screen.getByText('Attach the transfer receipt.')).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText(/Transfer reference number/), 'TRF-204918');
    const receipt = new File(['receipt'], 'receipt.png', { type: 'image/png' });
    await userEvent.upload(screen.getByLabelText(/Transfer receipt/), receipt);
    await userEvent.click(screen.getByRole('button', { name: 'Submit payment' }));

    await waitFor(() =>
      expect(createSettlement).toHaveBeenCalledWith(
        { method: 'bank_transfer', amount: '277.50', bankReference: 'TRF-204918', proof: receipt },
        expect.any(String),
      ),
    );
  });

  it('pays from the wallet, capped at the smaller of the debt and the balance', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '100.00' }));
    vi.mocked(listSettlements).mockResolvedValue(page());
    renderWithClient(<SettlementView />);

    await userEvent.click(await screen.findByRole('radio', { name: /From my wallet/ }));
    expect(screen.getByLabelText(/Amount/)).toHaveValue('100.00');
    expect(screen.queryByLabelText(/Transfer receipt/)).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Submit payment' }));

    await waitFor(() => expect(createSettlement).toHaveBeenCalledWith({ method: 'wallet', amount: '100.00' }, expect.any(String)));
  });

  it('holds new transfers while one is under review', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet());
    vi.mocked(listSettlements).mockResolvedValue(page([pendingTransfer]));
    renderWithClient(<SettlementView />);

    expect(await screen.findByText(/Your bank transfer of QAR 100.00 is waiting for review/)).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /Bank transfer/ })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Submit payment' })).not.toBeInTheDocument();
    expect(screen.getByText('TRF-1')).toBeInTheDocument();
  });

  it('lets the wallet pay only what a pending transfer does not cover', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '300.00' }));
    vi.mocked(listSettlements).mockResolvedValue(page([pendingTransfer]));
    renderWithClient(<SettlementView />);

    expect(await screen.findByRole('radio', { name: /From my wallet/ })).toBeChecked();
    expect(screen.getByLabelText(/Amount/)).toHaveValue('177.50');
    expect(screen.getByText('Up to QAR 177.50.')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Submit payment' }));

    await waitFor(() => expect(createSettlement).toHaveBeenCalledWith({ method: 'wallet', amount: '177.50' }, expect.any(String)));
  });

  it('thanks a seller who owes nothing', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ commission_debt: '0.00' }));
    vi.mocked(listSettlements).mockResolvedValue(page());
    renderWithClient(<SettlementView />);

    expect(await screen.findByText("You don't owe any commission. Thank you!")).toBeInTheDocument();
  });
});
