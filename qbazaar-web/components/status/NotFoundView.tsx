import type { ReactNode } from 'react';
import Link from 'next/link';
import { SearchX } from 'lucide-react';

import type { BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { buttonVariants } from '@/components/design-system/Button';
import { PageShell } from '@/components/design-system/PageShell';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { t } from '@/lib/i18n/messages';

interface NotFoundViewProps {
  /** Crumbs before the current page; the trail always starts at Home. */
  trail?: BreadcrumbItem[];
  heading?: string;
  description?: string;
  /** Replaces the default "Back to home" and "Help center" links. */
  actions?: ReactNode;
}

/** Missing page or record, built like the "Search Not Found" screens (655:55973, 655:55238, 608:26579). */
export function NotFoundView({ trail = [], heading, description, actions }: NotFoundViewProps) {
  const title = t('errors.not_found_title');

  return (
    <PageShell breadcrumb={[{ label: t('home.breadcrumb'), href: '/' }, ...trail, { label: title }]} title={title}>
      <StatePanel
        icon={<StateIcon icon={SearchX} tone="muted" />}
        title={heading ?? t('errors.not_found_heading')}
        description={description ?? t('errors.not_found_body')}
        action={
          <div className="flex flex-wrap justify-center gap-3">
            {actions ?? (
              <>
                <Link href="/" className={buttonVariants({ size: 'sm' })}>
                  {t('errors.back_home')}
                </Link>
                <Link href="/help" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
                  {t('help.title')}
                </Link>
              </>
            )}
          </div>
        }
      />
    </PageShell>
  );
}
