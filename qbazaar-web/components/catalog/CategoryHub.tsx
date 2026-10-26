'use client';

import { useId, useState } from 'react';
import Link from 'next/link';

import './catalog-tokens.css';

import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { GridViewIcon } from '@/components/design-system/icons';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { Category, CategorySection } from '@/lib/api/types';

import { AdRow } from './AdRow';
import { SubcategoryTile } from './CategoryTile';

/** Sections shown before the sub-category tiles, as in parent-category.html. */
const SECTIONS_BEFORE_TILES = 2;
/** Cards per section from 601 px: one row of four on desktop, two of two on tablets. */
const ROW_CARDS = 4;
/** Two rows of four tiles before "Show All". */
const INITIAL_TILES = 8;

/** The 22 px / 600 section title of parent-category.html. */
const sectionTitle = 'font-qb text-qb-h4 leading-normal font-semibold tracking-normal text-qb-ink';

interface CategoryHubProps {
  /** One per active sub-category, in display order (`GET /categories/{slug}`). */
  sections: CategorySection[] | undefined;
  isLoading: boolean;
}

/**
 * Overview of a parent category (parent-category.html): a row of the newest
 * ads per sub-category with "View All →", and the sub-category tiles after the
 * first two rows, 34 px apart.
 */
export function CategoryHub({ sections = [], isLoading }: CategoryHubProps) {
  if (isLoading) return <HubSkeleton />;

  const subcategories = sections.map((section) => section.category);
  const filled = sections.filter((section) => section.ads.length > 0);
  const before = filled.slice(0, SECTIONS_BEFORE_TILES);
  const after = filled.slice(SECTIONS_BEFORE_TILES);

  return (
    <div className="flex flex-col gap-[34px]">
      {before.map((section, index) => (
        <AdSection key={section.category.id} section={section} leading={index === 0} />
      ))}
      {filled.length === 0 ? (
        <EmptyState
          icon={<Icon icon={GridViewIcon} size="lg" />}
          title={t('catalog.empty.no_ads_in_category', 'لا توجد إعلانات في هذا القسم حتى الآن.')}
          className="rounded-qb-xl border border-qb-line bg-qb-surface"
        />
      ) : null}
      {subcategories.length > 0 ? <SubcategoryTiles subcategories={subcategories} /> : null}
      {after.map((section) => (
        <AdSection key={section.category.id} section={section} />
      ))}
    </div>
  );
}

function AdSection({ section, leading = false }: { section: CategorySection; leading?: boolean }) {
  const id = useId();
  const name = localized(section.category.name);
  return (
    <section aria-labelledby={id}>
      <div className="mb-4 flex items-center justify-between gap-4">
        <h2 id={id} className={cn(sectionTitle, 'min-w-0')}>
          {name}
        </h2>
        {/* The reference's orange `.qb-nav` link, whose arrow is text. */}
        <Link
          prefetch={false}
          href={`/c/${section.category.slug}`}
          className={cn('qb-motion flex shrink-0 items-center gap-[5px] font-qb text-qb-caption font-medium text-qb-brand hover:text-qb-brand motion-reduce:transition-none', focusRing)}
        >
          {t('catalog.view_all', 'عرض الكل')}
          <span aria-hidden="true" className="inline-block rtl:-scale-x-100">
            →
          </span>
        </Link>
      </div>
      <AdRow ads={section.ads} label={name} desktop="grid" desktopLimit={ROW_CARDS} leading={leading} />
    </section>
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
      <h2 id={id} className={cn(sectionTitle, 'mb-4')}>
        {t('catalog.hub.categories', 'الأقسام')}
      </h2>
      {/* `.qb-catgrid`: a swipeable row of 72 % tiles on phones, two columns on tablets, four on desktop. */}
      <ul
        id={listId}
        className="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden qb-tablet:[display:grid] qb-tablet:grid-cols-2 qb-tablet:gap-4 qb-tablet:overflow-visible qb-tablet:pb-0 qb-desktop:grid-cols-4"
      >
        {visible.map((child) => (
          <li key={child.id} className="w-[72%] shrink-0 snap-start qb-tablet:w-auto">
            <SubcategoryTile
              href={`/c/${child.slug}`}
              name={localized(child.name, locale)}
              count={tPlural('catalog.ads_count', child.ads_count)}
              className="h-full"
            />
          </li>
        ))}
      </ul>
      {subcategories.length > INITIAL_TILES ? (
        <div className="mt-[18px] flex justify-center">
          {/* The pale orange "Show All" of the reference. */}
          <Button
            aria-expanded={expanded}
            aria-controls={listId}
            onClick={() => setExpanded((value) => !value)}
            className="h-auto bg-(--color-qb-brand-pale) px-[30px] py-[11px] text-qb-caption leading-[1.15] font-medium hover:bg-(--color-qb-brand-pale) hover:brightness-[.96]"
          >
            {expanded ? t('catalog.view_less', 'عرض أقل') : t('catalog.view_more', 'عرض المزيد')}
          </Button>
        </div>
      ) : null}
    </section>
  );
}

function HubSkeleton() {
  const card = 'animate-pulse rounded-qb-xl border border-qb-line bg-qb-surface motion-reduce:animate-none';
  return (
    <div aria-busy="true" className="flex flex-col gap-[34px]">
      <span className="sr-only">{t('common.loading', 'جاري التحميل…')}</span>
      {Array.from({ length: 2 }, (_, row) => (
        <div key={row} aria-hidden="true">
          <div className="mb-4 h-[33px] w-48 animate-pulse rounded-qb-sm bg-qb-line motion-reduce:animate-none" />
          <div className="flex gap-3 overflow-hidden qb-tablet:[display:grid] qb-tablet:grid-cols-[repeat(auto-fill,minmax(220px,1fr))] qb-tablet:gap-[18px]">
            {Array.from({ length: ROW_CARDS }, (_, index) => (
              <div key={index} className={cn(card, 'h-[320px] w-[72%] shrink-0 qb-tablet:w-auto', index >= 2 && 'max-qb-tablet:hidden')} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
