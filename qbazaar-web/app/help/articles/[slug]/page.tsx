import type { Metadata } from 'next';
import { HelpArticleClient } from './HelpArticleClient';

interface PageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { slug } = await params;
  return { title: slug };
}

export default async function HelpArticlePage({ params }: PageProps) {
  const { slug } = await params;
  return <HelpArticleClient slug={slug} />;
}
