import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { SellerTabs } from './SellerTabs';

beforeEach(() => setClientLocale('en'));

describe('SellerTabs', () => {
  it('opens on the ads and keeps the reviews unmounted until their tab is chosen', () => {
    render(<SellerTabs ads={<p>Active Listings</p>} reviews={<p>Seller reviews</p>} />);

    expect(screen.getByRole('tab', { name: 'Ads' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tab', { name: 'Reviews' })).toHaveAttribute('aria-selected', 'false');
    expect(screen.getByText('Active Listings')).toBeVisible();
    expect(screen.queryByText('Seller reviews')).not.toBeInTheDocument();
  });

  it('shows the reviews under "Reviews"', async () => {
    render(<SellerTabs ads={<p>Active Listings</p>} reviews={<p>Seller reviews</p>} />);

    await userEvent.click(screen.getByRole('tab', { name: 'Reviews' }));

    expect(screen.getByRole('tab', { name: 'Reviews' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tabpanel')).toHaveTextContent('Seller reviews');
  });

  it('uses the flat underline tabs of seller.html', () => {
    render(<SellerTabs ads={<p>Active Listings</p>} reviews={<p>Seller reviews</p>} />);

    expect(screen.getByRole('tablist')).toHaveClass('border-b', 'gap-7');
    expect(screen.getByRole('tab', { name: 'Ads' })).toHaveClass('border-b-2', 'data-active:border-qb-brand');
  });

  it('labels the tabs in Arabic', () => {
    setClientLocale('ar');
    render(<SellerTabs ads={<p>Active Listings</p>} reviews={<p>Seller reviews</p>} />);

    expect(screen.getByRole('tab', { name: 'الإعلانات' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'التقييمات' })).toBeInTheDocument();
  });
});
