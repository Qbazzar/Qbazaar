'use client';

import { DirectionProvider } from '@base-ui/react/direction-provider';
import { Tabs as TabsPrimitive } from '@base-ui/react/tabs';

import { dirFor, getLocale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';

/** Base UI also accepts a className function; these wrappers take plain strings only. */
type WithClassName<P> = Omit<P, 'className'> & { className?: string };

/**
 * Pill tabs of the notifications and my-ads screens. Built on Base UI, so the
 * tablist roles, roving focus and arrow keys come for free; the direction
 * provider makes the arrow keys follow the reading direction in Arabic.
 */
export function Tabs({ className, ...props }: WithClassName<TabsPrimitive.Root.Props>) {
  return (
    <DirectionProvider direction={dirFor(getLocale())}>
      <TabsPrimitive.Root className={cn('font-qb', className)} {...props} />
    </DirectionProvider>
  );
}

export function TabList({ className, ...props }: WithClassName<TabsPrimitive.List.Props>) {
  return <TabsPrimitive.List className={cn('flex flex-wrap gap-3 qb-desktop:gap-4', className)} {...props} />;
}

export function Tab({ className, ...props }: WithClassName<TabsPrimitive.Tab.Props>) {
  return (
    <TabsPrimitive.Tab
      className={cn(
        'inline-flex h-10 cursor-pointer items-center justify-center rounded-qb-pill border border-qb-line bg-qb-hover px-4 text-qb-caption whitespace-nowrap text-qb-ink shadow-qb-card transition-colors',
        'qb-tablet:h-[53px] qb-tablet:px-8 qb-tablet:text-qb-body-lg qb-desktop:h-14 qb-desktop:text-qb-h5',
        'hover:bg-qb-surface data-active:border-qb-brand data-active:bg-qb-brand data-active:font-semibold data-active:text-qb-on-brand',
        'disabled:pointer-events-none disabled:opacity-50',
        focusRing,
        className,
      )}
      {...props}
    />
  );
}

export function TabPanel({ className, ...props }: WithClassName<TabsPrimitive.Panel.Props>) {
  return <TabsPrimitive.Panel className={cn('mt-6 rounded-qb-md', focusRing, className)} {...props} />;
}
