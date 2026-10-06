import { Suspense } from 'react';
import type { Metadata } from 'next';

import type { CategoryNode } from '@/lib/api/types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl, breadcrumbJsonLd, fetchApiData } from '@/lib/seo';
import { findCategoryPath } from '@/store/categories';
import { JsonLd } from '@/components/seo/JsonLd';
import { CatalogPageSkeleton } from '@/components/catalog/CatalogPageSkeleton';
import { resolveCategoryPath } from '@/components/catalog/resolved-category';
import { CategoryDetailClient } from './CategoryDetailClient';

interface PageProps {
  params: Promise<{ slug: string }>;
}

/**
 * Root-to-category path from the (cached) tree, or null. A miss is not a 404
 * here: the cached tree can predate a new category, so the client decides.
 */
async function findPath(slug: string): Promise<CategoryNode[] | null> {
  const tree = await fetchApiData<CategoryNode[]>('/api/v1/categories/tree');
  return findCategoryPath(tree, slug);
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  await resolveServerLocale();

  const path = await findPath(slug);
  const name = (path && localized(path[path.length - 1].name)) || slug;
  const url = absoluteUrl(`/c/${slug}`);

  return {
    title: name,
    alternates: { canonical: url },
    openGraph: { title: name, url, type: 'website' },
  };
}

/**
 * Category page — `/c/{slug}`. The server resolves the category for the
 * title, the breadcrumb JSON-LD and the first paint of the header; the
 * listing itself is a client island over the existing queries.
 */
export default async function CategoryDetailPage({ params }: PageProps) {
  const { slug } = await params;
  await resolveServerLocale();

  const path = await findPath(slug);
  const category = path ? resolveCategoryPath(path) : null;

  const breadcrumb = breadcrumbJsonLd([
    { name: t('brand.name', 'QBazaar'), path: '/' },
    { name: t('categories.all', 'الأقسام'), path: '/categories' },
    ...(category?.trail ?? [{ slug, name: slug }]).map((crumb) => ({ name: crumb.name, path: `/c/${crumb.slug}` })),
  ]);

  return (
    <>
      <JsonLd data={breadcrumb} />
      <Suspense fallback={<CatalogPageSkeleton title={category?.trail.at(-1)?.name} />}>
        <CategoryDetailClient slug={slug} initial={category} />
      </Suspense>
    </>
  );
}
