'use client';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import { siteFrame } from '@/components/design-system/site-frame';
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
  className?: string;
}

const SKELETON_CARDS = 4;

/** One ad slider section of the home page ("Recommended for you", "Best Selling"); hidden without ads. */
export function HomeAdSection({ id, title, subtitle, action, ads, isLoading, className }: HomeAdSectionProps) {
  if (!isLoading && !ads?.length) return null;

  return (
    <section aria-labelledby={id} className={cn(siteFrame, 'py-6', className)}>
      <SectionHeader id={id} title={title} subtitle={subtitle} action={action} className="mb-[22px]" />
      {isLoading ? (
        <div className="flex gap-5 overflow-hidden" aria-busy="true">
          {Array.from({ length: SKELETON_CARDS }, (_, i) => (
            <div
              key={i}
              aria-hidden="true"
              className="h-[288px] w-[clamp(258px,23vw,300px)] shrink-0 animate-pulse rounded-qb-xl border border-qb-line bg-qb-surface motion-reduce:animate-none"
            />
          ))}
        </div>
      ) : (
        <AdRail label={title}>
          {(ads ?? []).map((ad) => (
            <AdSummaryCard key={ad.id} ad={ad} />
          ))}
        </AdRail>
      )}
    </section>
  );
}
