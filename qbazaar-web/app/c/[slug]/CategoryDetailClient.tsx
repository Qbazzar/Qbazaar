'use client';

/**
 * `/c/[slug]`: a parent category opens on its overview (one row of newest ads
 * per sub-category, 18:912); a leaf category, or any filtered, sorted or paged
 * URL, shows the plain listing (69:467). The server passes the category it
 * resolved so the header and the right view render before the tree loads.
 */
import { notFound, usePathname, useRouter, useSearchParams } from 'next/navigation';

import type { BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { AdsListing } from '@/components/catalog/AdsListing';
import { CatalogHeader, CatalogStats, todayStat } from '@/components/catalog/CatalogHeader';
import { CatalogLayout } from '@/components/catalog/CatalogLayout';
import { CatalogPageSkeleton } from '@/components/catalog/CatalogPageSkeleton';
import { CategoryHub } from '@/components/catalog/CategoryHub';
import { FilterSheet, FilterSidebar } from '@/components/catalog/filters/CatalogFilters';
import type { FilterGroupKey } from '@/components/catalog/filters/FilterPanel';
import { EMPTY_FILTERS, type FilterValues } from '@/components/catalog/filters/filter-values';
import { hasListingParams, listingSearch, parseListingQuery } from '@/components/catalog/listing-query';
import { ListingToolbar } from '@/components/catalog/ListingToolbar';
import { LoadError } from '@/components/catalog/LoadError';
import { resolveCategoryPath, type ResolvedCategory } from '@/components/catalog/resolved-category';
import { SaveSearchButton } from '@/components/search/SaveSearchButton';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { useCategoryPageQuery, useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { findCategoryPath } from '@/store/categories';
import type { CategoryNode } from '@/lib/api/types';

interface Props {
  slug: string;
  /** Resolved on the server; null when its tree request failed. */
  initial: ResolvedCategory | null;
}

function breadcrumbOf(trail: ResolvedCategory['trail']): BreadcrumbItem[] {
  return [
    { label: t('home.breadcrumb', 'الرئيسية'), href: '/' },
    ...trail.map((crumb, index) => ({ label: crumb.name, href: index < trail.length - 1 ? `/c/${crumb.slug}` : undefined })),
  ];
}

export function CategoryDetailClient({ slug, initial }: Props) {
  const searchParams = useSearchParams();
  const treeQuery = useCategoryTreeQuery();
  const path = findCategoryPath(treeQuery.data, slug);

  if (treeQuery.data && !path) notFound();

  const node = path?.at(-1) ?? null;
  const category = path ? resolveCategoryPath(path) : initial;

  if (!category) {
    return treeQuery.isError ? <CategoryLoadError onRetry={() => treeQuery.refetch()} /> : <CatalogPageSkeleton />;
  }

  const title = category.trail.at(-1)?.name ?? slug;
  const breadcrumb = breadcrumbOf(category.trail);

  if (!category.hasChildren || hasListingParams(searchParams)) {
    return <AdsListing category={{ id: category.id, slug: category.slug }} heading={() => ({ title, breadcrumb })} />;
  }
  return <CategoryOverview slug={category.slug} title={title} breadcrumb={breadcrumb} node={node} />;
}

interface OverviewProps {
  slug: string;
  title: string;
  breadcrumb: BreadcrumbItem[];
  /** The tree node once the tree has loaded: counters until the page data arrives. */
  node: CategoryNode | null;
}

/** Parent category overview; applying a filter switches the same URL to the listing. */
function CategoryOverview({ slug, title, breadcrumb, node }: OverviewProps) {
  const locale = getLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const pageQuery = useCategoryPageQuery(slug);
  const { data: locations } = useQatarLocationsQuery();
  const { filters } = parseListingQuery(searchParams);

  const applyFilters = (next: FilterValues) => router.push(`${pathname}${listingSearch(searchParams, { filters: next })}`, { scroll: false });
  const groups: FilterGroupKey[] = ['price', 'location'];
  const panel = {
    groups,
    values: filters,
    onApply: applyFilters,
    onReset: () => applyFilters(EMPTY_FILTERS),
    locations,
  };

  const page = pageQuery.data;
  const counts = page ? page.category : node;
  const subCount = page?.sub_category_count ?? node?.children.length;
  const stats =
    counts && subCount !== undefined
      ? [
          { value: formatNumber(counts.ads_count, locale), label: t('catalog.stats.ads', 'إعلان') },
          { value: formatNumber(subCount, locale), label: t('catalog.stats.subcategories', 'قسم فرعي') },
          ...todayStat(counts.today_count, t('catalog.stats.today', 'اليوم'), locale),
        ]
      : [];
  const saveParams = { category_slug: slug };

  return (
    <CatalogLayout
      header={
        <CatalogHeader
          title={title}
          breadcrumb={breadcrumb}
          stats={<CatalogStats items={stats} />}
          actions={<SaveSearchButton params={saveParams} />}
        />
      }
      sidebar={<FilterSidebar {...panel} />}
      toolbar={<ListingToolbar filters={<FilterSheet {...panel} />} actions={<SaveSearchButton params={saveParams} variant="toolbar" />} />}
      toolbarBelowDesktopOnly
    >
      {pageQuery.isError ? (
        <LoadError title={t('catalog.empty.load_failed', 'تعذّر تحميل الإعلانات. حاول مرة أخرى.')} onRetry={() => pageQuery.refetch()} />
      ) : (
        <CategoryHub sections={page?.sections} isLoading={pageQuery.isLoading} />
      )}
    </CatalogLayout>
  );
}

function CategoryLoadError({ onRetry }: { onRetry: () => void }) {
  return (
    <CatalogLayout header={<CatalogHeader title={t('categories.all', 'الأقسام')} />}>
      <LoadError title={t('common.error', 'حدث خطأ، حاول مرة أخرى')} onRetry={onRetry} />
    </CatalogLayout>
  );
}
