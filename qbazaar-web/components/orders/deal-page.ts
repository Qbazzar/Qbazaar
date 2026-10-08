import 'server-only';

import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import type { DealAd } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { fetchApiData } from '@/lib/seo';

/** The product page's revalidation; the API checks the ad again when the request is sent. */
const AD_REVALIDATE_SECONDS = 300;

function fetchDealAd(id: string): Promise<DealAd | null> {
  return fetchApiData<DealAd>(`/api/v1/ads/${encodeURIComponent(id)}`, AD_REVALIDATE_SECONDS);
}

/** Title of the Buy Now or Make an Offer page of an ad, kept out of search results. */
export async function dealPageMetadata(id: string, titleKey: string): Promise<Metadata> {
  await resolveServerLocale();
  const ad = await fetchDealAd(id);
  const title = t(titleKey);
  return { title: ad ? `${title} · ${ad.title}` : title, robots: { index: false } };
}

/** The ad a deal page is about, or the 404 page when it is gone. */
export async function loadDealAd(id: string): Promise<DealAd> {
  await resolveServerLocale();
  const ad = await fetchDealAd(id);
  if (!ad) notFound();
  return ad;
}
