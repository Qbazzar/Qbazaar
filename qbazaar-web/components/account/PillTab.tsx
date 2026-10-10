'use client';

import { Tabs as TabsPrimitive } from '@base-ui/react/tabs';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

type PillTabProps = Omit<TabsPrimitive.Tab.Props, 'className'> & { className?: string };

/**
 * Filter pill of the notifications screen (notifTabs in notifications.html),
 * reused by My Ads and the tickets: 18 px, 500 in both states; the current
 * one orange, the others white with a soft shadow. Goes inside the design
 * system's `TabList` for the roles and the arrow keys.
 */
export function PillTab({ className, ...props }: PillTabProps) {
  return (
    <TabsPrimitive.Tab
      className={cn(
        'inline-flex h-[34px] shrink-0 cursor-pointer items-center justify-center rounded-[40px] bg-qb-surface px-4 font-qb text-[13.5px] font-medium whitespace-nowrap text-qb-ink shadow-qb-acct-pill transition-colors',
        'qb-tablet:h-[53px] qb-tablet:px-8 qb-tablet:text-qb-body-lg',
        'data-active:bg-qb-brand data-active:text-qb-on-brand data-active:shadow-none',
        'disabled:pointer-events-none disabled:opacity-50',
        focusRing,
        className,
      )}
      {...props}
    />
  );
}
