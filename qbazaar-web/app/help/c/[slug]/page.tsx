import type { Metadata } from 'next';
import { HelpCategoryDetailClient } from './HelpCategoryDetailClient';

interface PageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { slug } = await params;
  return { title: slug };
}

export default async function HelpCategoryPage({ params }: PageProps) {
  const { slug } = await params;
  return <HelpCategoryDetailClient slug={slug} />;
}
