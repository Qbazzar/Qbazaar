import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
const mutateAsync = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), info: vi.fn(), success: vi.fn() } }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));
vi.mock('@/lib/queries/messaging', () => ({
  useStartConversationMutation: () => ({ mutateAsync, isPending: false }),
}));

import type { DealAd } from '@/lib/api/commerce-types';
import type { PublicUser, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

import { AdSellerCard } from './AdSellerCard';

const seller: PublicUser = {
  id: 'seller',
  full_name: 'Mark Toro',
  avatar_url: null,
  account_type: 'private',
  business_name: null,
  joined_at: '2016-01-08T10:00:00+00:00',
  verification_badges: { email_verified: true, phone_verified: true, business_verified: false },
  ads_count: 3,
  rating_avg: 4.5,
  rating_count: 12,
  followers_count: 124,
  following_count: 2,
};

const ad = { id: 'ad-1', user_id: 'seller', status: 'active', price: 1500, price_type: 'fixed', ad_type: 'offering' } as DealAd;

function renderCard(props: Partial<Parameters<typeof AdSellerCard>[0]> = {}) {
  const client = new QueryClient();
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  return render(<AdSellerCard ad={ad} seller={seller} isOwner={false} locale="en" {...props} />, { wrapper });
}

beforeEach(() => {
  setClientLocale('en');
  useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
  vi.clearAllMocks();
});

describe('AdSellerCard', () => {
  it('introduces the seller with a link to their page', () => {
    renderCard();

    expect(screen.getByRole('link', { name: /Mark Toro/ })).toHaveAttribute('href', '/u/seller');
    expect(screen.getByRole('img', { name: 'Verified seller' })).toBeInTheDocument();
    expect(screen.getAllByText('Private Seller').length).toBeGreaterThan(0);
    expect(screen.getByText('4.5 · 12 reviews')).toBeInTheDocument();
    expect(screen.getByText('3 Ads')).toBeInTheDocument();
    expect(screen.getByText('Active since 08.01.2016')).toBeInTheDocument();
  });

  it('leaves out "Active since" when the join date is missing', () => {
    renderCard({ seller: { ...seller, joined_at: undefined as unknown as string } });

    expect(screen.queryByText(/Active since/)).not.toBeInTheDocument();
  });

  it('offers "Buy Now", "Make an Offer" and "Send Message" to buyers', () => {
    renderCard();

    expect(screen.getByRole('link', { name: 'Buy Now' })).toHaveAttribute('href', '/ads/ad-1/buy');
    expect(screen.getByRole('link', { name: 'Make an Offer' })).toHaveAttribute('href', '/ads/ad-1/offer');
    expect(screen.getByRole('button', { name: 'Send Message' })).toBeEnabled();
  });

  it.each([
    ['without a price', { price: null, price_type: 'contact' }],
    ['given away', { price: null, price_type: 'free' }],
    ['looking to buy', { ad_type: 'wanted' }],
  ] as const)('has no "Buy Now" on an ad %s', (_, change) => {
    renderCard({ ad: { ...ad, ...change } as DealAd });

    expect(screen.queryByRole('link', { name: 'Buy Now' })).toBeNull();
    expect(screen.getByRole('link', { name: 'Make an Offer' })).toBeInTheDocument();
  });

  it('takes no deal on a reserved ad, only messages', () => {
    renderCard({ ad: { ...ad, is_reserved: true } });

    expect(screen.queryByRole('link', { name: 'Buy Now' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Make an Offer' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Send Message' })).toBeInTheDocument();
  });

  it('opens the conversation for a verified buyer', async () => {
    useAuthStore.setState({ user: { id: 'buyer', phone_verified: true } as User, accessToken: 'AT', isHydrated: true });
    mutateAsync.mockResolvedValue({ id: 'conv-9' });
    renderCard();

    await userEvent.click(screen.getByRole('button', { name: 'Send Message' }));

    expect(mutateAsync).toHaveBeenCalledWith('ad-1');
    await waitFor(() => expect(push).toHaveBeenCalledWith('/account/messages?c=conv-9'));
  });

  it('sends a guest to login before contacting', async () => {
    renderCard();

    await userEvent.click(screen.getByRole('button', { name: 'Send Message' }));

    expect(mutateAsync).not.toHaveBeenCalled();
    expect(push).toHaveBeenCalledWith(`/login?from=${encodeURIComponent('/ads/ad-1')}`);
  });

  it('tells the owner it is their ad instead of the contact actions', () => {
    renderCard({ isOwner: true });

    expect(screen.getByText('This is your ad')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Make an Offer' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Buy Now' })).toBeNull();
  });

  it('marks a sold ad and takes no contact, but the buyer can still rate the seller', () => {
    useAuthStore.setState({ user: { id: 'buyer', phone_verified: true } as User, accessToken: 'AT', isHydrated: true });
    renderCard({ ad: { ...ad, status: 'sold' } });

    expect(screen.getByText('Sold')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Send Message' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Rate the seller' })).toBeInTheDocument();
  });

  it('shows the owner of a sold ad that it is sold', () => {
    renderCard({ ad: { ...ad, status: 'sold' }, isOwner: true });

    expect(screen.getByText('Sold')).toBeInTheDocument();
    expect(screen.queryByText('This is your ad')).toBeNull();
  });

  it('still offers the actions when the ad came without its seller', () => {
    renderCard({ seller: undefined });

    expect(screen.queryByRole('link', { name: /Mark Toro/ })).toBeNull();
    expect(screen.getByRole('link', { name: 'Make an Offer' })).toBeInTheDocument();
  });
});
