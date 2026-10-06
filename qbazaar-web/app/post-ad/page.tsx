import type { Metadata } from 'next';

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
      <div className="mx-auto w-full max-w-[1440px] px-qb-gutter py-[clamp(20px,4vw,40px)] font-qb text-qb-ink">
        <PostAdClient />
      </div>
    </main>
  );
}
