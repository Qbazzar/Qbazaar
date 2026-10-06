import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { BusinessProfile } from '@/lib/api/types';

import { CompanyTabs, isCompanyTab } from './CompanyTabs';

const business: BusinessProfile = {
  business_name: 'BonTon',
  about: 'Real estate in West Bay since 2010.',
  legal_name: 'BonTon Real Estate W.L.L.',
  commercial_registration_number: '78901',
  contact_phone: null,
  contact_email: 'legal@bonton.qa',
  website: null,
  address: 'Diplomatic Area, Doha',
  opening_hours: [],
  cover_url: null,
  updated_at: null,
};

beforeEach(() => setClientLocale('en'));

function renderTabs(props: Partial<Parameters<typeof CompanyTabs>[0]> = {}) {
  return render(
    <CompanyTabs business={business} adsCount={8429} locale="en" defaultTab="ads" ads={<p>ads grid</p>} {...props} />,
  );
}

describe('CompanyTabs', () => {
  it('opens on the ads, with their count in the tab', () => {
    renderTabs();

    expect(screen.getByRole('tab', { name: 'Ads (8,429)' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByText('ads grid')).toBeVisible();
  });

  it('shows the company description under "About us"', async () => {
    renderTabs();

    await userEvent.click(screen.getByRole('tab', { name: 'About us' }));

    expect(screen.getByText('Real estate in West Bay since 2010.')).toBeVisible();
  });

  it('shows the imprint and contact under "Legal Info"', () => {
    renderTabs({ defaultTab: 'legal' });

    expect(screen.getByRole('heading', { name: 'Imprint' })).toBeInTheDocument();
    expect(screen.getByText('BonTon Real Estate W.L.L.')).toBeInTheDocument();
    expect(screen.getByText('78901')).toBeInTheDocument();
    expect(screen.getByText('Email: legal@bonton.qa')).toBeInTheDocument();
  });

  it('says when the company has not filled a tab in', async () => {
    renderTabs({ business: null, defaultTab: 'about' });

    expect(screen.getByText("This company hasn't added a description yet.")).toBeVisible();
    await userEvent.click(screen.getByRole('tab', { name: 'Legal Info' }));
    expect(screen.getByText("This company hasn't added its legal details yet.")).toBeVisible();
  });
});

describe('isCompanyTab', () => {
  it('accepts the three tab names only', () => {
    expect(isCompanyTab('legal')).toBe(true);
    expect(isCompanyTab('reviews')).toBe(false);
    expect(isCompanyTab(null)).toBe(false);
  });
});
