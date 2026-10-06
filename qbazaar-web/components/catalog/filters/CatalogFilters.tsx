'use client';

import { useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Sheet } from '@/components/design-system/Modal';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { headingFont } from '../layout';

import { countActiveFilters, type FilterValues } from './filter-values';
import { FilterPanel, type FilterPanelProps } from './FilterPanel';

/** A new set of applied filters remounts the panel, which resets its draft. */
const panelKey = (values: FilterValues) => JSON.stringify(values);

/** The filter card beside the results, from 1001 px up. */
export function FilterSidebar(props: FilterPanelProps) {
  return (
    <aside
      aria-label={t('catalog.filters.title', 'الفلتر')}
      className="hidden w-[356px] shrink-0 self-start rounded-qb-2xl border border-qb-line bg-qb-surface shadow-qb-card qb-desktop:block"
    >
      <FilterPanel key={panelKey(props.values)} variant="sidebar" {...props} />
    </aside>
  );
}

interface FilterSheetProps extends FilterPanelProps {
  /** Classes for the trigger, which sits in the toolbar pill. */
  triggerClassName?: string;
}

/** "Filter" button of the toolbar under 1001 px and the bottom sheet it opens (618:26974). */
export function FilterSheet({ triggerClassName, onApply, onReset, ...props }: FilterSheetProps) {
  const [open, setOpen] = useState(false);
  const active = countActiveFilters(props.values);

  return (
    <Sheet
      open={open}
      onOpenChange={setOpen}
      title={t('catalog.filters.sheet_title', 'الفلاتر')}
      className={headingFont}
      trigger={
        <button
          type="button"
          className={cn(
            'inline-flex h-full shrink-0 cursor-pointer items-center gap-2 rounded-qb-md px-3 font-qb text-qb-micro text-qb-ink qb-tablet:px-4 qb-tablet:text-qb-body',
            focusRing,
            triggerClassName,
          )}
        >
          {t('catalog.filters.open', 'فلتر')}
          <Icon icon={SlidersHorizontal} size="sm" className="qb-tablet:size-5" />
          {active > 0 ? (
            <span className="inline-flex min-w-[18px] items-center justify-center rounded-qb-pill bg-qb-brand px-1 text-qb-tiny leading-[18px] font-semibold text-white">
              {active}
              <span className="sr-only"> {t('catalog.filters.active_suffix', 'فلاتر مفعّلة')}</span>
            </span>
          ) : null}
        </button>
      }
    >
      <FilterPanel
        key={panelKey(props.values)}
        variant="sheet"
        {...props}
        onApply={(next) => {
          setOpen(false);
          onApply(next);
        }}
        onReset={() => {
          setOpen(false);
          onReset();
        }}
      />
    </Sheet>
  );
}
