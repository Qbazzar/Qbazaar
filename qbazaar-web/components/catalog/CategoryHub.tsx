'use client';

import { useId, useState } from 'react';
import { ChevronDown, LayoutGrid } from 'lucide-react';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { SectionHeader } from '@/components/design-system/SectionHeader';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdSummary, Category, CategorySection } from '@/lib/api/types';

import { SubcategoryTile } from './CategoryTile';
import { catalogBleed } from './layout';

/** Sections shown before the sub-category tiles, as in 18:912. */
const SECTIONS_BEFORE_TILES = 2;
/** Cards per section on desktop; tablets and phones scroll through all of them. */
const DESKTOP_CARDS = 3;
/** Tiles shown before "View More". */
const INITIAL_TILES = 6;

interface CategoryHubProps {
  /** One per active sub-category, in display order (`GET /categories/{slug}`). */
  sections: CategorySection[] | undefined;
  isLoading: boolean;
}

/**
 * Overview of a parent category: a row of the newest ads per sub-category
 * with "View All", and the sub-category tiles after the first two rows
 * (18:912, 539:35503, 623:28688).
 */
export function CategoryHub({ sections = [], isLoading }: CategoryHubProps) {
  if (isLoading) return <HubSkeleton />;

  const subcategories = sections.map((section) => section.category);
  const filled = sections.filter((section) => section.ads.length > 0);
  const before = filled.slice(0, SECTIONS_BEFORE_TILES);
  const after = filled.slice(SECTIONS_BEFORE_TILES);

  return (
    <div className="flex flex-col gap-12 qb-tablet:gap-[38px] qb-desktop:gap-8">
      {before.map((section) => (
        <AdSection key={section.category.id} section={section} />
      ))}
      {filled.length === 0 ? (
        <EmptyState
          icon={<Icon icon={LayoutGrid} size="lg" />}
          title={t('catalog.empty.no_ads_in_category', 'لا توجد إعلانات في هذا القسم حتى الآن.')}
          className="rounded-qb-2xl border border-qb-line bg-qb-surface"
        />
      ) : null}
      {subcategories.length > 0 ? <SubcategoryTiles subcategories={subcategories} /> : null}
      {after.map((section) => (
        <AdSection key={section.category.id} section={section} />
      ))}
    </div>
  );
}

function AdSection({ section }: { section: CategorySection }) {
  const id = useId();
  const name = localized(section.category.name);
  return (
    <section aria-labelledby={id}>
      <SectionHeader
        id={id}
        title={name}
        action={{ href: `/c/${section.category.slug}`, label: t('catalog.view_all', 'عرض الكل') }}
        className="mb-[26px] qb-tablet:mb-[38px]"
      />
      <AdRow ads={section.ads} label={name} />
    </section>
  );
}

/** Newest ads of one sub-category: three cards on desktop, a swipeable row below. */
function AdRow({ ads, label }: { ads: AdSummary[]; label: string }) {
  return (
    <ul
      aria-label={label}
      className={cn(
        catalogBleed,
        'flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 [scrollbar-width:none] qb-tablet:gap-3.5 [&::-webkit-scrollbar]:hidden',
        'qb-desktop:mx-0 qb-desktop:[display:grid] qb-desktop:grid-cols-3 qb-desktop:gap-[17px] qb-desktop:overflow-visible qb-desktop:px-0 qb-desktop:pb-0',
      )}
    >
      {ads.map((ad, index) => (
        <li key={ad.id} className={cn('w-[250px] shrink-0 snap-start qb-tablet:w-[310px] qb-desktop:w-auto', index >= DESKTOP_CARDS && 'qb-desktop:hidden')}>
          <AdSummaryCard ad={ad} className="h-full" />
        </li>
      ))}
    </ul>
  );
}

function SubcategoryTiles({ subcategories }: { subcategories: Category[] }) {
  const locale = getLocale();
  const id = useId();
  const [expanded, setExpanded] = useState(false);
  const visible = expanded ? subcategories : subcategories.slice(0, INITIAL_TILES);
  const listId = `${id}-list`;

  return (
    <section aria-labelledby={id}>
      <h2 id={id} className="mb-[26px] font-qb text-qb-h5 leading-none font-semibold tracking-normal text-qb-ink qb-tablet:mb-[38px] qb-tablet:text-qb-h2">
        {t('catalog.hub.categories', 'الأقسام')}
      </h2>
      <ul id={listId} className="[display:grid] grid-cols-2 gap-2 qb-tablet:gap-x-[18px] qb-tablet:gap-y-4 qb-desktop:grid-cols-3 qb-desktop:gap-4">
        {visible.map((child) => (
          <li key={child.id}>
            <SubcategoryTile
              href={`/c/${child.slug}`}
              name={localized(child.name, locale)}
              icon={child.icon}
              count={t('categories.ads_count', { count: formatNumber(child.ads_count, locale) }, `${formatNumber(child.ads_count, locale)} إعلان`)}
              className="h-full"
            />
          </li>
        ))}
      </ul>
      {subcategories.length > INITIAL_TILES ? (
        <div className="mt-[22px] flex justify-center qb-tablet:mt-6">
          <Button size="sm" aria-expanded={expanded} aria-controls={listId} onClick={() => setExpanded((value) => !value)} className="font-normal qb-tablet:text-qb-body">
            {expanded ? t('catalog.view_less', 'عرض أقل') : t('catalog.view_more', 'عرض المزيد')}
            <Icon icon={ChevronDown} size="sm" className={cn('transition-transform motion-reduce:transition-none', expanded && 'rotate-180')} />
          </Button>
        </div>
      ) : null}
    </section>
  );
}

function HubSkeleton() {
  const card = 'animate-pulse rounded-qb-xl border border-qb-line bg-qb-surface motion-reduce:animate-none';
  return (
    <div aria-busy="true" className="flex flex-col gap-12 qb-desktop:gap-8">
      <span className="sr-only">{t('common.loading', 'جاري التحميل…')}</span>
      {Array.from({ length: 2 }, (_, row) => (
        <div key={row} aria-hidden="true">
          <div className="mb-[26px] h-7 w-48 animate-pulse rounded-qb-sm bg-qb-line motion-reduce:animate-none qb-tablet:mb-[38px]" />
          <div className="flex gap-3 overflow-hidden qb-desktop:[display:grid] qb-desktop:grid-cols-3 qb-desktop:gap-[17px]">
            {Array.from({ length: DESKTOP_CARDS }, (_, index) => (
              <div key={index} className={cn(card, 'h-[270px] w-[250px] shrink-0 qb-tablet:w-[310px] qb-desktop:w-auto')} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
