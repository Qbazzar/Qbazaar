import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/ads', () => ({ getMyAds: vi.fn() }));
vi.mock('@/lib/api/promotions', () => ({ listMyPromotions: vi.fn() }));

import { getMyAds } from '@/lib/api/ads';
import { listMyPromotions } from '@/lib/api/promotions';
import type { AdSummary, PaginatedResponse, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { renderWithClient } from '@/components/orders/test-utils';
import { useAuthStore } from '@/store/auth';

import { PromotionsView } from './PromotionsView';

function adsPage(page: number, lastPage: number, titles: string[]): PaginatedResponse<AdSummary> {
  return {
    data: titles.map((title, index) => ({ id: `ad-${page}-${index}`, title, primary_image: null }) as unknown as AdSummary),
    meta: { current_page: page, last_page: lastPage, per_page: 20, total: lastPage * 20 },
    links: { first: null, last: null, prev: null, next: null },
  };
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'seller-1' } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(listMyPromotions).mockResolvedValue({ success: true, data: [], meta: { per_page: 20, has_more: false, next_cursor: null } });
});

describe('PromotionsView', () => {
  it('pages through the live ads 20 at a time, as the API serves them', async () => {
    vi.mocked(getMyAds).mockImplementation(async (params = {}) =>
      params.page === 2 ? adsPage(2, 2, ['Canon EOS R6']) : adsPage(1, 2, ['Sony WH-1000XM5']),
    );
    renderWithClient(<PromotionsView />);

    expect(await screen.findByText('Sony WH-1000XM5')).toBeInTheDocument();
    expect(getMyAds).toHaveBeenCalledWith({ status: 'active', page: 1 });

    await userEvent.click(screen.getByRole('button', { name: 'Next page' }));

    expect(await screen.findByText('Canon EOS R6')).toBeInTheDocument();
    await waitFor(() => expect(getMyAds).toHaveBeenCalledWith({ status: 'active', page: 2 }));
  });
});
