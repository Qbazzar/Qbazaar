import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { BusinessProfile } from '@/lib/api/types';

import { CompanyInfoSheet } from './CompanyInfoSheet';

const business = {
  business_name: 'BonTon',
  about: null,
  legal_name: null,
  commercial_registration_number: null,
  contact_phone: '+97444123456',
  contact_email: null,
  website: null,
  address: null,
  opening_hours: [],
  cover_url: null,
  updated_at: null,
} as BusinessProfile;

beforeEach(() => setClientLocale('en'));

describe('CompanyInfoSheet', () => {
  it('slides the contact card up from "Info" and closes on Escape', async () => {
    render(<CompanyInfoSheet business={business} locale="en" />);

    await userEvent.click(screen.getByRole('button', { name: 'Info' }));
    expect(await screen.findByRole('dialog', { name: 'Info' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '+97444123456' })).toHaveAttribute('href', 'tel:+97444123456');

    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
  });
});
