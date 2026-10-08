import type { Metadata } from 'next';

import { sellerDisplayName } from '@/components/users/SellerBadges';
import type { PublicUserProfile } from '@/lib/api/types';
import { absoluteUrl, fetchApiData } from '@/lib/seo';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { SellerProfileClient } from './SellerProfileClient';

interface PageProps {
  params: Promise<{ id: string }>;
}

/** Cached for five minutes; the metadata and the page read the same entry. */
function fetchProfile(id: string): Promise<PublicUserProfile | null> {
  return fetchApiData<PublicUserProfile>(`/api/v1/users/${encodeURIComponent(id)}/public-profile`, 300);
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { id } = await params;
  const [profile] = await Promise.all([fetchProfile(id), resolveServerLocale()]);
  if (!profile) return { title: t('users.profile.not_found_title') };

  const name = sellerDisplayName(profile);
  const description = profile.business_profile?.about?.replace(/\s+/g, ' ').trim().slice(0, 160) || undefined;
  return {
    title: name,
    description,
    alternates: { canonical: absoluteUrl(`/u/${id}`) },
    openGraph: { title: name, description, url: absoluteUrl(`/u/${id}`), type: 'profile' },
  };
}

/**
 * Public seller page — `/u/{id}`. The server fetches the anonymous profile
 * for the metadata and the first render; the client island refetches it
 * with the viewer's session.
 */
export default async function PublicProfilePage({ params }: PageProps) {
  const { id } = await params;
  const profile = await fetchProfile(id);
  return <SellerProfileClient id={id} initialProfile={profile ?? undefined} />;
}
