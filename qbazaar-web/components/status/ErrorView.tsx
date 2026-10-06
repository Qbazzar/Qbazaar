import Link from 'next/link';
import { CircleAlert } from 'lucide-react';

import type { BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { Button, buttonVariants } from '@/components/design-system/Button';
import { PageShell } from '@/components/design-system/PageShell';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { t } from '@/lib/i18n/messages';

interface ErrorViewProps {
  onRetry: () => void;
  /** Disables the retry button while a retry is in flight. */
  retrying?: boolean;
  /** Crumbs before the current page; the trail always starts at Home. */
  trail?: BreadcrumbItem[];
}

/** Failed load with a retry, in the same panel as the not-found screens. */
export function ErrorView({ onRetry, retrying = false, trail = [] }: ErrorViewProps) {
  const title = t('errors.generic_title');

  return (
    <PageShell breadcrumb={[{ label: t('home.breadcrumb'), href: '/' }, ...trail, { label: title }]} title={title}>
      <StatePanel
        icon={<StateIcon icon={CircleAlert} tone="muted" />}
        title={t('errors.generic_heading')}
        description={t('errors.generic_body')}
        action={
          <div className="flex flex-wrap justify-center gap-3">
            <Button size="sm" onClick={onRetry} disabled={retrying}>
              {t('errors.retry')}
            </Button>
            <Link href="/" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              {t('errors.back_home')}
            </Link>
          </div>
        }
      />
    </PageShell>
  );
}
