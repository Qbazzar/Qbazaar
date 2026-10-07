import type { Metadata } from 'next';

import { absoluteUrl } from '@/lib/seo';

import { HelpArticleClient } from './HelpArticleClient';

interface PageProps {
  params: Promise<{ slug: string }>;
}

// The article is read in the browser only: every API read of an article
// counts a view, so a server read for the title would inflate its views.
export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;

  return {
    title: slug,
    alternates: { canonical: absoluteUrl(`/help/articles/${slug}`) },
  };
}

export default async function HelpArticlePage({ params }: PageProps) {
  const { slug } = await params;
  return <HelpArticleClient slug={slug} />;
}
