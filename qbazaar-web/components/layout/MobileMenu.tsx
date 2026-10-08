'use client';

import { useState } from 'react';
import dynamic from 'next/dynamic';
import { Menu } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** 44px bordered square of the phone header (globe, bell, burger). */
export const mobileIconButton = cn(
  'relative inline-flex size-11 cursor-pointer items-center justify-center rounded-qb-lg border border-qb-line bg-qb-surface text-qb-ink shadow-qb-control',
  focusRing,
);

// The drawer and its dialog code load on demand: every page renders the
// header, but only phone visitors who open the menu need them.
const loadDrawer = () => import('./MobileMenuDrawer');
const MobileMenuDrawer = dynamic(() => loadDrawer().then((module) => module.MobileMenuDrawer), { ssr: false });

/** Burger button of the phone and small-tablet header; opens the navigation drawer. */
export function MobileMenu({ signedIn }: { signedIn: boolean }) {
  const [open, setOpen] = useState(false);
  const [requested, setRequested] = useState(false);
  const prefetch = () => void loadDrawer();

  return (
    <>
      <button
        type="button"
        aria-label={t('layout.menu.open', 'فتح القائمة')}
        aria-haspopup="dialog"
        aria-expanded={open}
        onPointerEnter={prefetch}
        onFocus={prefetch}
        onClick={() => {
          setRequested(true);
          setOpen(true);
        }}
        className={mobileIconButton}
      >
        <Icon icon={Menu} strokeWidth={2} />
      </button>
      {requested ? <MobileMenuDrawer open={open} onOpenChange={setOpen} signedIn={signedIn} /> : null}
    </>
  );
}
