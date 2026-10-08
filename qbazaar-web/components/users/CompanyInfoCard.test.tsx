import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { BusinessProfile } from '@/lib/api/types';

import { CompanyInfoCard, hasCompanyContacts, safeWebsiteHref } from './CompanyInfoCard';

const business: BusinessProfile = {
  business_name: 'BonTon',
  about: null,
  legal_name: null,
  commercial_registration_number: null,
  contact_phone: '+97444123456',
  contact_email: 'hello@bonton.qa',
  website: 'https://www.bonton.qa/',
  address: 'West Bay, Doha',
  opening_hours: [
    { day: 'sun', closed: false, open: '09:00', close: '18:00' },
    { day: 'mon', closed: false, open: '09:00', close: '18:00' },
    { day: 'fri', closed: true, open: null, close: null },
  ],
  cover_url: null,
  updated_at: null,
};

beforeEach(() => setClientLocale('en'));

describe('safeWebsiteHref', () => {
  it('accepts web links only', () => {
    expect(safeWebsiteHref('https://bonton.qa')).toBe('https://bonton.qa/');
    expect(safeWebsiteHref('javascript:alert(1)')).toBeNull();
    expect(safeWebsiteHref('not a url')).toBeNull();
  });
});

describe('hasCompanyContacts', () => {
  it('is false without a business profile or any contact detail', () => {
    expect(hasCompanyContacts(null)).toBe(false);
    expect(
      hasCompanyContacts({ ...business, contact_phone: null, contact_email: null, website: null, address: null, opening_hours: [] }),
    ).toBe(false);
    expect(hasCompanyContacts(business)).toBe(true);
  });
});

describe('CompanyInfoCard', () => {
  it('links the phone, email and website and groups the opening hours', () => {
    render(<CompanyInfoCard business={business} locale="en" />);

    expect(screen.getByRole('heading', { name: 'Info' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '+97444123456' })).toHaveAttribute('href', 'tel:+97444123456');
    expect(screen.getByRole('link', { name: 'hello@bonton.qa' })).toHaveAttribute('href', 'mailto:hello@bonton.qa');
    const website = screen.getByRole('link', { name: 'www.bonton.qa' });
    expect(website).toHaveAttribute('href', 'https://www.bonton.qa/');
    expect(website).toHaveAttribute('rel', expect.stringContaining('noopener'));
    expect(screen.getByText('West Bay, Doha')).toBeInTheDocument();
    expect(screen.getByText('09:00 – 18:00')).toBeInTheDocument();
    expect(screen.getByText('Closed')).toBeInTheDocument();
  });

  it('shows an unsafe website as text', () => {
    render(<CompanyInfoCard business={{ ...business, website: 'javascript:alert(1)' }} locale="en" />);

    expect(screen.queryByRole('link', { name: /javascript/ })).toBeNull();
    expect(screen.getByText('javascript:alert(1)')).toBeInTheDocument();
  });
});
