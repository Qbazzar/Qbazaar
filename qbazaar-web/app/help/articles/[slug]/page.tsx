import type { Metadata } from 'next';

import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl } from '@/lib/seo';

import { HelpArticleClient } from './HelpArticleClient';

interface PageProps {
  params: Promise<{ slug: string }>;
}

// The article is read in the browser only: every API read of an article
// counts a view, so a server read would inflate the article's view count.
export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  await resolveServerLocale();

  return {
    title: t('help.title'),
    alternates: { canonical: absoluteUrl(`/help/articles/${slug}`) },
  };
}

export default async function HelpArticlePage({ params }: PageProps) {
  const { slug } = await params;
  return <HelpArticleClient slug={slug} />;
}
