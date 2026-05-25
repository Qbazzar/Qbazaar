/**
 * CMS page — `/p/{slug}`.
 *
 * Server-rendered with ISR (revalidate every 60 minutes). The body is admin-
 * authored markdown that the backend already sanitises; we render it inside
 * a `.cms-prose` block. A missing slug or unpublished page short-circuits to
 * the Next.js 404 page via `notFound()`.
 */
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { MarkdownContent } from '@/components/cms/MarkdownContent';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { Page, SuccessEnvelope } from '@/lib/api/types';

interface PageProps {
  params: Promise<{ slug: string }>;
}

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4010';

async function fetchPage(slug: string): Promise<Page | null> {
  try {
    const res = await fetch(
      `${API_BASE}/api/v1/pages/${encodeURIComponent(slug)}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 3600 },
      },
    );
    if (res.status === 404) return null;
    if (!res.ok) return null;
    const envelope = (await res.json()) as SuccessEnvelope<Page>;
    return envelope.data ?? null;
  } catch {
    return null;
  }
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const page = await fetchPage(slug);
  if (!page) return { title: slug };
  const locale = getLocale();
  const title = localized(page.title, locale);
  const description = localized(page.meta_description, locale);
  return {
    title,
    description: description || undefined,
  };
}

export default async function CmsPage({ params }: PageProps) {
  const { slug } = await params;
  const page = await fetchPage(slug);
  if (!page) notFound();

  const locale = getLocale();
  const title = localized(page.title, locale);
  const body = localized(page.body, locale);

  return (
    <main className="page-section">
      <div className="container" style={{ maxWidth: 820 }}>
        <header className="mb-8">
          <h1 className="text-h1 text-ink-900">{title}</h1>
        </header>
        <MarkdownContent html={body} />
        <p className="text-ink-500 mt-12 text-xs">
          {t('cms.last_updated', 'آخر تحديث')}:{' '}
          {page.published_at ? new Date(page.published_at).toLocaleDateString() : '—'}
        </p>
      </div>
    </main>
  );
}
