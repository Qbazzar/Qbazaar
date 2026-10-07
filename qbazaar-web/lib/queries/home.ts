/**
 * TanStack Query hook for the home feed. Every home section reads the same
 * cached query, so the page sends one request for all of them. The API
 * refreshes the feed every two minutes, so a fresher client copy buys nothing.
 */
import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import type { ApiClientError } from '@/lib/api/auth';
import { getHomeFeed, type HomeFeed } from '@/lib/api/home';
import { getLocale, type Locale } from '@/lib/i18n/locale';

const FEED_STALE_MS = 2 * 60 * 1000;

export const homeKeys = {
  all: ['home'] as const,
  feed: (locale: Locale) => [...homeKeys.all, 'feed', locale] as const,
};

export function useHomeFeedQuery(): UseQueryResult<HomeFeed, ApiClientError> {
  const locale = getLocale();
  return useQuery({
    queryKey: homeKeys.feed(locale),
    queryFn: () => getHomeFeed(locale),
    staleTime: FEED_STALE_MS,
  });
}
