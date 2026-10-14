'use client';

import { Tabs as TabsPrimitive } from '@base-ui/react/tabs';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

type PillTabProps = Omit<TabsPrimitive.Tab.Props, 'className'> & { className?: string };

/**
 * Filter pill of the notifications screen (notifTabs in notifications.html),
 * reused by My Ads and the tickets: a `.qb-btn` of 18 px, 500 in both states
 * on a 1.15 line; the current one orange (#e96c3f under the pointer), the
 * others white with a soft shadow that dim 4 % under the pointer, all with
 * the polish transition. Goes inside the design system's `TabList` for the
 * roles and the arrow keys.
 */
export function PillTab({ className, ...props }: PillTabProps) {
  return (
    <TabsPrimitive.Tab
      className={cn(
        'qb-motion inline-flex h-[34px] shrink-0 cursor-pointer items-center justify-center rounded-[40px] bg-qb-surface px-4 font-qb text-[13.5px] leading-[1.15] font-medium whitespace-nowrap text-qb-ink shadow-qb-acct-pill hover:brightness-[.96]',
        'qb-tablet:h-[53px] qb-tablet:px-8 qb-tablet:text-qb-body-lg qb-tablet:leading-[1.15]',
        'data-active:bg-qb-brand data-active:text-qb-on-brand data-active:shadow-none data-active:hover:bg-qb-brand-hover data-active:hover:filter-none',
        'disabled:pointer-events-none disabled:opacity-50',
        focusRing,
        className,
      )}
      {...props}
    />
  );
}
