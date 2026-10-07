/**
 * CMS page — `/p/{slug}`.
 *
 * No Figma frame: the page frame of notifications (455:14636) with the body
 * in the white panel of the empty wishlist (376:7817). The body is admin-
 * authored HTML that the backend sanitises (and MarkdownContent again). A
 * missing or unpublished page renders the 404 page; any other API failure
 * reaches the error boundary instead of posing as a missing page.
 */
import { cache } from 'react';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { ContentPanel } from '@/components/cms/ContentPanel';
import { MarkdownContent } from '@/components/cms/MarkdownContent';
import { PageShell } from '@/components/design-system/PageShell';
import { fetchPublicResource } from '@/lib/api/public-resource';
import type { Page } from '@/lib/api/types';
import { intlLocale } from '@/lib/i18n/format';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl } from '@/lib/seo';

interface PageProps {
  params: Promise<{ slug: string }>;
}

/** Memoized per request, so the metadata and the page share one read. */
const fetchPage = cache(
  (slug: string): Promise<Page | null> => fetchPublicResource<Page>(`/api/v1/pages/${encodeURIComponent(slug)}`),
);

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  await resolveServerLocale();

  const page = await fetchPage(slug);
  if (!page) return { title: t('errors.not_found_title') };

  return {
    title: localized(page.title),
    description: localized(page.meta_description) || undefined,
    alternates: { canonical: absoluteUrl(`/p/${slug}`) },
  };
}

export default async function CmsPage({ params }: PageProps) {
  const { slug } = await params;
  await resolveServerLocale();

  const page = await fetchPage(slug);
  if (!page) notFound();

  const title = localized(page.title);

  return (
    <PageShell
      breadcrumb={[{ label: t('home.breadcrumb'), href: '/' }, { label: title }]}
      title={title}
      meta={page.published_at ? <LastUpdated iso={page.published_at} /> : undefined}
    >
      <ContentPanel>
        <MarkdownContent html={localized(page.body)} />
      </ContentPanel>
    </PageShell>
  );
}

function LastUpdated({ iso }: { iso: string }) {
  const date = new Intl.DateTimeFormat(intlLocale(getLocale()), { dateStyle: 'long', timeZone: 'Asia/Qatar' }).format(new Date(iso));

  return (
    <>
      {t('cms.last_updated')}: <time dateTime={iso}>{date}</time>
    </>
  );
}
