'use client';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import type { AdSummary } from '@/lib/api/types';

interface ResponsiveListCardProps {
  ad: AdSummary;
  eager?: boolean;
}

/**
 * The list card from 1001 px (69:467) and, below it, the grid card of the
 * swipeable overview rows (539:35503, 623:28688). Both render and CSS shows one.
 */
export function ResponsiveListCard({ ad, eager = false }: ResponsiveListCardProps) {
  return (
    <>
      <AdSummaryCard ad={ad} eager={eager} layout="grid" className="h-full qb-desktop:hidden" />
      <AdSummaryCard ad={ad} eager={eager} layout="list" className="hidden qb-desktop:flex" />
    </>
  );
}
