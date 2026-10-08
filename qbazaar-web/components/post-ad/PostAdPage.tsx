import type { ReactNode } from 'react';
import { LoaderCircle } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import { pageGutter } from '@/components/design-system/page-gutter';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** Breadcrumb above the form; it drops to 16 px on phones (633:33029). */
export const formBreadcrumbClass = 'mb-6 max-qb-tablet:text-qb-body qb-tablet:mb-8';

/**
 * Page frame of add-ads.html (324:13009, 528:19541, 638:36172), shared by
 * /post-ad and the edit page so both lay out the same way.
 */
export function PostAdFrame({ children }: { children: ReactNode }) {
  return <div className={cn('mx-auto w-full max-w-[1440px] py-[clamp(20px,4vw,40px)] font-qb text-qb-ink', pageGutter)}>{children}</div>;
}

export function PostAdLoading() {
  return (
    <div role="status" className="flex min-h-[60vh] items-center justify-center">
      <Icon icon={LoaderCircle} size="lg" label={t('common.loading')} className="animate-spin text-qb-ink-subtle motion-reduce:animate-none" />
    </div>
  );
}
