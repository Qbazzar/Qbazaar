import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/api/ads', () => ({ getAd: vi.fn() }));
vi.mock('@/lib/api/promotions', () => ({ listPromotionOffers: vi.fn(), purchasePromotion: vi.fn() }));
vi.mock('@/lib/api/wallet', () => ({ getWallet: vi.fn() }));

import { toast } from 'sonner';

import { getAd } from '@/lib/api/ads';
import { ApiClientError } from '@/lib/api/auth';
import type { AdPromotion, PromotionOffer } from '@/lib/api/commerce-types';
import { listPromotionOffers, purchasePromotion } from '@/lib/api/promotions';
import type { Ad, User } from '@/lib/api/types';
import { getWallet } from '@/lib/api/wallet';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildWallet } from '@/lib/orders/test-fixtures';
import { renderWithClient } from '@/components/orders/test-utils';
import { useAuthStore } from '@/store/auth';

import { PromoteAdView } from './PromoteAdView';

const offers: PromotionOffer[] = [
  { type: 'highlight', price: '15.00', currency: 'QAR', duration_days: 7 },
  { type: 'premium', price: '50.00', currency: 'QAR', duration_days: 14 },
];

const ad = { id: 'ad-2', user_id: 'seller-1', title: 'Sony WH-1000XM5', status: 'active' } as Ad;

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'seller-1' } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(getAd).mockResolvedValue(ad);
  vi.mocked(listPromotionOffers).mockResolvedValue(offers);
});

describe('PromoteAdView', () => {
  it('pays from the wallet when the withdrawable amount covers the price', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '80.00', withdrawable_balance: '60.00', commission_debt: '20.00' }));
    vi.mocked(purchasePromotion).mockResolvedValue({ status: 'active', ends_at: '2026-10-20T10:00:00Z' } as AdPromotion);
    renderWithClient(<PromoteAdView adId="ad-2" />);

    await userEvent.click(await screen.findByRole('radio', { name: /Premium/ }));
    expect(screen.getByRole('radio', { name: /Wallet/ })).toBeChecked();
    await userEvent.click(screen.getByRole('button', { name: 'Promote for QAR 50.00' }));

    await waitFor(() =>
      expect(purchasePromotion).toHaveBeenCalledWith(
        'ad-2',
        { type: 'premium', payment_method: 'wallet', transfer_reference: null },
        expect.any(String),
      ),
    );
    expect(toast.success).toHaveBeenCalledWith('Promotion active until October 20, 2026.');
    expect(push).toHaveBeenCalledWith('/account/promotions');
  });

  it('falls back to a bank transfer when the commission owed blocks the wallet', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '10.00', withdrawable_balance: '0.00' }));
    vi.mocked(purchasePromotion).mockResolvedValue({ status: 'pending_payment', ends_at: null } as AdPromotion);
    renderWithClient(<PromoteAdView adId="ad-2" />);

    expect(await screen.findByRole('radio', { name: /Wallet/ })).toBeDisabled();
    expect(screen.getByText(/Your wallet can cover at most QAR 0.00/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Promote for QAR 15.00' }));
    expect(await screen.findByText('This field is required.')).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText(/Transfer reference/), 'TRX-204918');
    await userEvent.click(screen.getByRole('button', { name: 'Promote for QAR 15.00' }));

    await waitFor(() =>
      expect(purchasePromotion).toHaveBeenCalledWith(
        'ad-2',
        { type: 'highlight', payment_method: 'bank_transfer', transfer_reference: 'TRX-204918' },
        expect.any(String),
      ),
    );
  });

  it('switches to a bank transfer when the API answers WALLET_003', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet({ available_balance: '80.00', withdrawable_balance: '60.00' }));
    vi.mocked(purchasePromotion).mockRejectedValueOnce(
      new ApiClientError({ status: 422, code: 'WALLET_003', messageKey: 'x', message: 'Too much', details: { withdrawable: ['5.00'] } }),
    );
    renderWithClient(<PromoteAdView adId="ad-2" />);

    await userEvent.click(await screen.findByRole('button', { name: 'Promote for QAR 15.00' }));

    expect(await screen.findByText(/Your wallet can cover at most QAR 5.00/)).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /Bank transfer/ })).toBeChecked();
  });

  it('refuses ads of other users', async () => {
    vi.mocked(getWallet).mockResolvedValue(buildWallet());
    vi.mocked(getAd).mockResolvedValue({ ...ad, user_id: 'someone-else' });
    renderWithClient(<PromoteAdView adId="ad-2" />);

    expect(await screen.findByText("We couldn't find this ad among yours.")).toBeInTheDocument();
  });
});
