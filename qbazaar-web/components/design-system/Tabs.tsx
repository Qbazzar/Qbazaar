'use client';

import { createContext, useContext } from 'react';
import { DirectionProvider } from '@base-ui/react/direction-provider';
import { Tabs as TabsPrimitive } from '@base-ui/react/tabs';

import { dirFor, getLocale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';

/** Base UI also accepts a className function; these wrappers take plain strings only. */
type WithClassName<P> = Omit<P, 'className'> & { className?: string };

/**
 * `pill`: the rounded chips of the notifications and my-ads screens.
 * `underline`: the flat bar of seller.html, where the chosen tab is orange
 * over a 2 px orange line on the bar's grey rule.
 */
export type TabsVariant = 'pill' | 'underline';

const VariantContext = createContext<TabsVariant>('pill');

/**
 * Tabs built on Base UI, so the tablist roles, roving focus and arrow keys
 * come for free; the direction provider makes the arrow keys follow the
 * reading direction in Arabic. The variant set here styles every part.
 */
export function Tabs({ className, variant = 'pill', ...props }: WithClassName<TabsPrimitive.Root.Props> & { variant?: TabsVariant }) {
  return (
    <DirectionProvider direction={dirFor(getLocale())}>
      <VariantContext.Provider value={variant}>
        <TabsPrimitive.Root className={cn('font-qb', className)} {...props} />
      </VariantContext.Provider>
    </DirectionProvider>
  );
}

/** Keeps the tabs on one line on phones and scrolls them sideways, edge to edge, instead of wrapping. */
const scrollOnPhonesClass =
  'max-qb-tablet:-mx-4 max-qb-tablet:flex-nowrap max-qb-tablet:overflow-x-auto max-qb-tablet:px-4 max-qb-tablet:py-2 max-qb-tablet:[scrollbar-width:none]';

const listClassName: Record<TabsVariant, string> = {
  pill: 'flex flex-wrap gap-3 qb-desktop:gap-4',
  // 28 px apart on a 1 px #ededed rule; a long row wraps, as the reference's flex-wrap does.
  underline: 'flex flex-wrap gap-7 border-b border-qb-line',
};

export function TabList({
  className,
  scrollOnPhones = false,
  ...props
}: WithClassName<TabsPrimitive.List.Props> & { scrollOnPhones?: boolean }) {
  const variant = useContext(VariantContext);
  return (
    <TabsPrimitive.List
      className={cn(listClassName[variant], scrollOnPhones ? scrollOnPhonesClass : null, className)}
      {...props}
    />
  );
}

const tabClassName: Record<TabsVariant, string> = {
  pill: cn(
    'inline-flex h-10 cursor-pointer items-center justify-center rounded-qb-pill border border-qb-line bg-qb-hover px-4 text-qb-caption whitespace-nowrap text-qb-ink',
    'qb-tablet:h-[53px] qb-tablet:px-8 qb-tablet:text-qb-body-lg qb-desktop:h-14 qb-desktop:text-qb-h5',
    // Only an inactive tab changes under the pointer (Figma 273:4687); the active one stays orange.
    'hover:bg-qb-hover data-active:border-qb-brand data-active:bg-qb-brand data-active:font-semibold data-active:text-qb-on-brand data-active:hover:bg-qb-brand',
  ),
  // 16 px / 500 on the font's own line height with 14 px below the label: #9e9696 over a clear line,
  // orange over an orange one when chosen. Nothing changes under the pointer and nothing animates.
  underline:
    'cursor-pointer border-b-2 border-transparent bg-transparent pb-3.5 text-qb-body leading-[normal] font-medium text-qb-ink-faint data-active:border-qb-brand data-active:text-qb-brand',
};

export function Tab({ className, ...props }: WithClassName<TabsPrimitive.Tab.Props>) {
  const variant = useContext(VariantContext);
  return (
    <TabsPrimitive.Tab
      className={cn(tabClassName[variant], 'disabled:pointer-events-none disabled:opacity-50', focusRing, className)}
      {...props}
    />
  );
}

// The underline bar keeps 22 px to its panel; its panels are square.
const panelClassName: Record<TabsVariant, string> = {
  pill: 'mt-6 rounded-qb-md',
  underline: 'mt-[22px]',
};

export function TabPanel({ className, ...props }: WithClassName<TabsPrimitive.Panel.Props>) {
  const variant = useContext(VariantContext);
  return <TabsPrimitive.Panel className={cn(panelClassName[variant], focusRing, className)} {...props} />;
}
