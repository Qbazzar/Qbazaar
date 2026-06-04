import type { Metadata } from 'next';

import type { CategoryNode } from '@/lib/api/types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl, breadcrumbJsonLd, fetchApiData } from '@/lib/seo';
import { JsonLd } from '@/components/seo/JsonLd';
import { CategoryDetailClient } from './CategoryDetailClient';

interface PageProps {
  params: Promise<{ slug: string }>;
}

function findBySlug(
  nodes: CategoryNode[] | null,
  slug: string,
): CategoryNode | null {
  if (!Array.isArray(nodes)) return null;

  for (const node of nodes) {
    if (node.slug === slug) return node;
    const found = findBySlug(node.children, slug);
    if (found) return found;
  }

  return null;
}

async function resolveCategoryName(slug: string): Promise<string> {
  const tree = await fetchApiData<CategoryNode[]>('/api/v1/categories');
  const node = findBySlug(tree, slug);
  return (node && localized(node.name)) || slug;
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { slug } = await params;
  await resolveServerLocale();

  const name = await resolveCategoryName(slug);
  const url = absoluteUrl(`/c/${slug}`);

  return {
    title: name,
    alternates: { canonical: url },
    openGraph: { title: name, url, type: 'website' },
  };
}

/**
 * Category detail — `/c/{slug}`.
 *
 * The listing surface is rendered by the client island; the server entrypoint
 * resolves the localized name for a real <title> and emits a Home › Categories
 * › {category} breadcrumb for crawlers.
 */
export default async function CategoryDetailPage({ params }: PageProps) {
  const { slug } = await params;
  const name = await resolveCategoryName(slug);

  const breadcrumb = breadcrumbJsonLd([
    { name: t('brand.name', 'QBazaar'), path: '/' },
    { name: t('categories.all', 'الأقسام'), path: '/categories' },
    { name, path: `/c/${slug}` },
  ]);

  return (
    <>
      <JsonLd data={breadcrumb} />
      <CategoryDetailClient slug={slug} />
    </>
  );
}
