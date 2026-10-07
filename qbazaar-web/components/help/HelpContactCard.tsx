import Link from 'next/link';

import { buttonVariants } from '@/components/design-system/Button';
import { Card } from '@/components/design-system/Card';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** "Still need help?" hand-off from the help center to a support ticket. */
export function HelpContactCard({ className }: { className?: string }) {
  return (
    <Card
      large
      elevated
      className={cn(
        'flex flex-col items-start gap-4 qb-tablet:flex-row qb-tablet:items-center qb-tablet:justify-between qb-tablet:gap-6',
        className,
      )}
    >
      <div className="min-w-0">
        <h2 className="font-qb text-qb-body-lg font-medium tracking-normal text-qb-ink qb-desktop:text-qb-h5">
          {t('help.contact_title')}
        </h2>
        <p className="mt-1 text-qb-caption text-qb-ink-muted qb-desktop:text-qb-body">{t('help.contact_body')}</p>
      </div>
      <Link href="/support/new" className={buttonVariants({ size: 'md' })}>
        {t('help.contact_action')}
      </Link>
    </Card>
  );
}
