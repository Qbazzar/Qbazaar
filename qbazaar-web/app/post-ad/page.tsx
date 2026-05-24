'use client';

/**
 * `/post-ad` — multi-step wizard for creating a new ad. Auth-required.
 *
 * Routing the unauth case through `useRequireAuth` matches the account-area
 * convention so refreshes don't kick the user out mid-flow.
 */
import { Loader2Icon } from 'lucide-react';
import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';
import { PostAdWizard } from '@/components/ads/PostAdWizard';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { t } from '@/lib/i18n/messages';

export default function PostAdPage() {
  const { user, isLoading } = useRequireAuth();

  if (isLoading || !user) {
    return (
      <div className="flex min-h-svh items-center justify-center" role="status">
        <Loader2Icon className="text-muted-foreground size-6 animate-spin" />
      </div>
    );
  }

  return (
    <main className="bg-cream-50 min-h-svh">
      <div className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
        <Link
          href="/"
          className="text-ink-500 mb-4 inline-flex items-center gap-1.5 text-sm hover:text-coral"
        >
          <ArrowLeft className="size-3.5 rtl:rotate-180" />
          {t('ads.post.cancel', 'العودة للرئيسية')}
        </Link>
        <h1 className="font-display text-4xl text-ink-900 md:text-5xl">
          {t('ads.post.title', 'انشر إعلاناً جديداً')}
        </h1>
        <p className="text-ink-500 mt-2 max-w-lg text-sm">
          {t(
            'ads.post.subtitle',
            'معظم الإعلانات تظهر للعموم خلال دقيقة. اكمل الخطوات لنشر إعلانك.',
          )}
        </p>
        <div className="mt-8">
          <PostAdWizard />
        </div>
      </div>
    </main>
  );
}
