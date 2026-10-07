/**
 * Typed client for `GET /api/v1/home`: every home section in one request.
 * The API rebuilds the feed every two minutes per language, so the page asks
 * for its own language instead of trusting the browser's Accept-Language
 * (the card chips and field labels follow it).
 */
import type { Locale } from '@/lib/i18n/locale';

import { api } from './client';
import type { AccountType, AdSummary, Category, LocalizedString, SuccessEnvelope } from './types';

/** A seller of the "Featured Companies" row (contract `SellerCard`). */
export interface HomeSeller {
  id: string;
  full_name: string;
  avatar_url: string | null;
  account_type: AccountType;
  /** Active ads of the seller. */
  ads_count: number;
  rating_avg: number;
  rating_count: number;
  /** Where the seller sells from (the place of their newest listed ad); null when unknown. */
  location?: { slug: string; name: LocalizedString } | null;
}

export interface HomeFeed {
  /** Active top-level categories with live counters. */
  categories: Category[];
  /** Most viewed ads of the last 30 days. */
  recommended: AdSummary[];
  /** Business accounts with the most live ads. */
  featured_sellers: HomeSeller[];
  /** Most favourited listed ads. */
  best_selling: AdSummary[];
}

export async function getHomeFeed(locale: Locale): Promise<HomeFeed> {
  const { data } = await api.get<SuccessEnvelope<HomeFeed>>('/api/v1/home', { params: { lang: locale } });
  return data.data;
}
