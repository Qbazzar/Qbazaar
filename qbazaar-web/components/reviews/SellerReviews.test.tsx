import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { SellerReviews } from './SellerReviews';

let queryClient: QueryClient;

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

beforeEach(() => {
  setClientLocale('en');
  queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
});

describe('SellerReviews', () => {
  it('shows its "Reviews" heading', () => {
    render(<SellerReviews userId="u1" ratingAvg={0} ratingCount={0} />, { wrapper });

    const heading = screen.getByRole('heading', { name: 'Reviews' });
    expect(heading).not.toHaveClass('sr-only');
    expect(screen.getByRole('region', { name: 'Reviews' })).toBeInTheDocument();
    expect(screen.getByText('No reviews yet.')).toHaveClass('mt-4');
  });

  it('keeps the heading for screen readers only under a "Reviews" tab', () => {
    render(<SellerReviews userId="u1" ratingAvg={0} ratingCount={0} titleHidden />, { wrapper });

    expect(screen.getByRole('heading', { name: 'Reviews' })).toHaveClass('sr-only');
    expect(screen.getByRole('region', { name: 'Reviews' })).toBeInTheDocument();
    // Nothing visible above the note, so it needs no gap.
    expect(screen.getByText('No reviews yet.')).not.toHaveClass('mt-4');
  });
});
