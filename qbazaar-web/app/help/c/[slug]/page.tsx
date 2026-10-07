import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { fetchHelpCategory } from '@/lib/api/help-server';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl } from '@/lib/seo';

import { HelpCategoryDetail } from './HelpCategoryDetail';

interface PageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  await resolveServerLocale();

  const category = await fetchHelpCategory(slug);
  if (!category) return { title: t('errors.not_found_title') };

  return {
    title: `${localized(category.name)} · ${t('help.title')}`,
    description: localized(category.description) || t('help.subtitle'),
    alternates: { canonical: absoluteUrl(`/help/c/${slug}`) },
  };
}

/** A missing topic answers 404; otherwise the topic renders on the server. */
export default async function HelpCategoryPage({ params }: PageProps) {
  const { slug } = await params;
  await resolveServerLocale();

  const category = await fetchHelpCategory(slug);
  if (!category) notFound();

  return <HelpCategoryDetail category={category} />;
}
