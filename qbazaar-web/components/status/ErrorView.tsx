import Link from 'next/link';

import type { BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { buttonVariants } from '@/components/design-system/Button';
import { PageShell } from '@/components/design-system/PageShell';
import { t } from '@/lib/i18n/messages';

import { RetryPanel } from './RetryPanel';

interface ErrorViewProps {
  onRetry: () => void;
  /** A retry is in flight. */
  retrying?: boolean;
  /** Crumbs before the current page; the trail always starts at Home. */
  trail?: BreadcrumbItem[];
}

/** Failed page with a retry, in the same panel as the not-found screens. */
export function ErrorView({ onRetry, retrying = false, trail = [] }: ErrorViewProps) {
  const title = t('errors.generic_title');

  return (
    <PageShell breadcrumb={[{ label: t('home.breadcrumb'), href: '/' }, ...trail, { label: title }]} title={title}>
      {/* The failed route's own title would otherwise stay on the tab. */}
      <title>{`${title} · QBazaar`}</title>
      <RetryPanel
        onRetry={onRetry}
        retrying={retrying}
        secondaryAction={
          <Link href="/" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
            {t('errors.back_home')}
          </Link>
        }
      />
    </PageShell>
  );
}
