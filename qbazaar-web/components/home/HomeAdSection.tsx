'use client';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import { SectionHeader } from '@/components/design-system/SectionHeader';
import type { AdSummary } from '@/lib/api/types';
import { cn } from '@/lib/utils';

import { AdRail } from './AdRail';

interface HomeAdSectionProps {
  id: string;
  title: string;
  subtitle: string;
  action?: { href: string; label: string };
  ads: AdSummary[] | undefined;
  isLoading: boolean;
  /** The first rail on the page loads its first photos eagerly. */
  eager?: boolean;
  className?: string;
}

const SKELETON_CARDS = 4;

/** One ad slider section of the home page ("Recommended for you", "Best Selling"); hidden without ads. */
export function HomeAdSection({ id, title, subtitle, action, ads, isLoading, eager, className }: HomeAdSectionProps) {
  if (!isLoading && !ads?.length) return null;

  return (
    <section aria-labelledby={id} className={cn('mx-auto max-w-[1440px] px-qb-gutter py-6', className)}>
      <SectionHeader id={id} title={title} subtitle={subtitle} action={action} className="mb-[22px]" />
      {isLoading ? (
        <div className="flex gap-5 overflow-hidden" aria-busy="true">
          {Array.from({ length: SKELETON_CARDS }, (_, i) => (
            <div
              key={i}
              aria-hidden="true"
              className="h-[288px] w-[clamp(258px,23vw,300px)] shrink-0 animate-pulse rounded-qb-xl border border-qb-line bg-qb-surface"
            />
          ))}
        </div>
      ) : (
        <AdRail label={title}>
          {(ads ?? []).map((ad, index) => (
            <AdSummaryCard key={ad.id} ad={ad} eager={eager && index < 4} />
          ))}
        </AdRail>
      )}
    </section>
  );
}
