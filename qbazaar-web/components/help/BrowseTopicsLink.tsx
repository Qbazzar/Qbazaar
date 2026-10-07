import Link from 'next/link';

import { buttonVariants } from '@/components/design-system/Button';
import { t } from '@/lib/i18n/messages';

/** Call to action of the help empty and not-found states. */
export function BrowseTopicsLink() {
  return (
    <Link href="/help" className={buttonVariants({ size: 'sm' })}>
      {t('help.browse_topics')}
    </Link>
  );
}
