'use client';

import { Dialog } from '@base-ui/react/dialog';
import { Info } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { BusinessProfile } from '@/lib/api/types';

import { CompanyInfoList, companyInfoTitle } from './CompanyInfoCard';

interface CompanyInfoSheetProps {
  business: BusinessProfile;
  locale: Locale;
  className?: string;
}

/**
 * Below 1001 px the company's "Info" card leaves the page: an "Info ⓘ"
 * trigger under the tabs slides it up from the bottom (mobilemenu.js
 * .qb-cardsheet): r22 top corners, the darker backdrop, a 0.34 s slide.
 * Esc and the backdrop close it.
 */
export function CompanyInfoSheet({ business, locale, className }: CompanyInfoSheetProps) {
  return (
    <Dialog.Root>
      <Dialog.Trigger
        className={cn(
          'inline-flex cursor-pointer items-center gap-[9px] rounded-qb-sm px-0.5 py-1 font-qb text-[17px] font-medium text-qb-ink',
          focusRing,
          className,
        )}
      >
        {t('users.profile.info')}
        <Icon icon={Info} strokeWidth={1.6} className="size-[18px]" />
      </Dialog.Trigger>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-overlay-strong transition-opacity duration-[250ms] data-ending-style:opacity-0 data-starting-style:opacity-0 motion-reduce:transition-none" />
        <Dialog.Popup
          className={cn(
            'fixed inset-x-0 bottom-0 z-50 max-h-[84vh] overflow-y-auto rounded-t-[22px] border border-qb-line bg-qb-surface px-6 pt-6 pb-[26px] font-qb text-qb-ink shadow-qb-sheet',
            'transition-transform duration-[340ms] ease-[cubic-bezier(.4,0,.2,1)] data-ending-style:translate-y-[106%] data-starting-style:translate-y-[106%] motion-reduce:transition-none',
            focusRing,
          )}
        >
          <Dialog.Title className={cn('font-qb', companyInfoTitle)}>{t('users.profile.info')}</Dialog.Title>
          <CompanyInfoList business={business} locale={locale} />
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
