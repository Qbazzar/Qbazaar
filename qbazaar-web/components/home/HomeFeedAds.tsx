'use client';

import { useHomeFeedQuery } from '@/lib/queries/home';

import { HomeAdSection } from './HomeAdSection';

interface HomeFeedAdsProps {
  /** Which ad list of the home feed the slider shows. */
  list: 'recommended' | 'best_selling';
  id: string;
  title: string;
  subtitle: string;
  /** The first slider on the page loads its first photos eagerly. */
  eager?: boolean;
  className?: string;
}

/**
 * "Recommended for you" or "Best Selling" from the home feed; hidden when the
 * list is empty or the feed failed before it had any data.
 */
export function HomeFeedAds({ list, id, title, subtitle, eager, className }: HomeFeedAdsProps) {
  const { data, isLoading, isError } = useHomeFeedQuery();

  return (
    <HomeAdSection
      id={id}
      title={title}
      subtitle={subtitle}
      ads={data?.[list] ?? (isError ? [] : undefined)}
      isLoading={isLoading}
      eager={eager}
      className={className}
    />
  );
}
