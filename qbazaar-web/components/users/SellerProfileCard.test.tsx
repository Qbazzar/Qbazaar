import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { PublicUserProfile } from '@/lib/api/types';

import { SellerProfileCard } from './SellerProfileCard';

const profile: PublicUserProfile = {
  id: 'seller',
  full_name: 'Mark Toro',
  avatar_url: null,
  account_type: 'private',
  business_name: null,
  joined_at: '2016-01-08T10:00:00+00:00',
  verification_badges: { email_verified: false, phone_verified: true, business_verified: false },
  ads_count: 3,
  rating_avg: 4.46,
  rating_count: 1,
  followers_count: 124,
  following_count: 0,
  is_following: false,
  business_profile: null,
};

beforeEach(() => setClientLocale('en'));

describe('SellerProfileCard', () => {
  it('heads the page with the seller and their kind', () => {
    render(<SellerProfileCard profile={profile} locale="en" actions={<button type="button">Follow</button>} />);

    expect(screen.getByRole('heading', { level: 1, name: /Mark Toro/ })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Verified seller' })).toBeInTheDocument();
    expect(screen.getByText('Private Seller')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Follow' })).toBeInTheDocument();
  });

  it('lists followers, membership date and ads count', () => {
    render(<SellerProfileCard profile={profile} locale="en" actions={null} />);
    const info = screen.getByRole('region', { name: 'Info' });

    expect(within(info).getByText('Followers').closest('div')).toHaveTextContent('124');
    expect(within(info).getByText('Member Since').closest('div')).toHaveTextContent('Jan 08, 2016');
    expect(within(info).getByText('Ads no.').closest('div')).toHaveTextContent('3');
  });

  it('turns the verifications and the rating into highlights', () => {
    render(<SellerProfileCard profile={profile} locale="en" actions={null} />);
    const highlights = screen.getByRole('region', { name: 'Seller Highlights' });

    expect(within(highlights).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Verified phone',
      '4.5 · 1 review',
    ]);
  });

  it('has no highlights section when there is nothing to highlight', () => {
    render(
      <SellerProfileCard
        profile={{ ...profile, rating_count: 0, verification_badges: { email_verified: false, phone_verified: false, business_verified: false } }}
        locale="en"
        actions={null}
      />,
    );

    expect(screen.queryByRole('region', { name: 'Seller Highlights' })).toBeNull();
    expect(screen.queryByRole('img', { name: 'Verified seller' })).toBeNull();
  });
});
