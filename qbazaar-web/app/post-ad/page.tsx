import type { Metadata } from 'next';

import { PostAdFrame } from '@/components/post-ad/PostAdPage';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

import { PostAdClient } from './PostAdClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('post_ad.page_title') };
}

/** `/post-ad`: add-ads.html → preview.html → publish.html (FE-16.5). */
export default function PostAdPage() {
  return (
    <main className="min-h-svh bg-qb-page">
      <PostAdFrame>
        <PostAdClient />
      </PostAdFrame>
    </main>
  );
}
