'use client';

/**
 * `/post-ad` — multi-step wizard for creating a new ad. Auth-required.
 *
 * QBFront port of the page chrome (source: QBFront/post.html) — the wizard
 * body itself keeps its existing internals (FE-3.x complex form). Scope-cut:
 * the inner step UI may visually drift slightly from the prototype's
 * `.cat-pick`/`.photo-slot`/`.tier-pick` styles; the chrome around it now
 * matches.
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
    <main>
      <div className="container post-wizard" style={{ paddingTop: 32, paddingBottom: 64 }}>
        <Link
          href="/"
          className="breadcrumbs"
          style={{ display: 'inline-flex', textDecoration: 'none' }}
        >
          <ArrowLeft className="size-3.5 rtl:rotate-180" />
          {t('ads.post.cancel', 'العودة للرئيسية')}
        </Link>

        <div style={{ marginTop: 16, marginBottom: 32 }}>
          <p className="step-title__kicker">
            {t('ads.post.kicker', 'انشر إعلان جديد')}
          </p>
          <h1 className="step-title__h">
            {t('ads.post.title', 'انشر إعلاناً جديداً')}
          </h1>
          <p className="step-title__sub">
            {t(
              'ads.post.subtitle',
              'معظم الإعلانات تظهر للعموم خلال دقيقة. اكمل الخطوات لنشر إعلانك.',
            )}
          </p>
        </div>

        <PostAdWizard />
      </div>
    </main>
  );
}
