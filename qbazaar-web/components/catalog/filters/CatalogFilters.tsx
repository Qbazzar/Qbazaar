'use client';

import { useState } from 'react';
import { ListFilter } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Sheet } from '@/components/design-system/Modal';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';

import { headingFont } from '../layout';
import { useRequestResultsFocus } from '../results-focus';

import { countActiveFilters, type FilterValues } from './filter-values';
import { FilterPanel, type FilterPanelProps } from './FilterPanel';

/** The page's handlers, sending the focus to the results once they change. */
function useFocusingHandlers({ onApply, onReset }: Pick<FilterPanelProps, 'onApply' | 'onReset'>) {
  const requestResultsFocus = useRequestResultsFocus();
  return {
    onApply: (next: FilterValues) => {
      requestResultsFocus();
      onApply(next);
    },
    onReset: () => {
      requestResultsFocus();
      onReset();
    },
  };
}

/** The filter card beside the results, from 1001 px up. */
export function FilterSidebar({ onApply, onReset, ...props }: FilterPanelProps) {
  const focusing = useFocusingHandlers({ onApply, onReset });
  return (
    <aside
      aria-label={t('catalog.filters.title', 'الفلتر')}
      className="hidden w-[356px] shrink-0 self-start rounded-qb-2xl border border-qb-line bg-qb-surface shadow-qb-card qb-desktop:block"
    >
      {/* No focus request for the chips: a removed chip hands the focus to the next one. */}
      <FilterPanel variant="sidebar" {...props} {...focusing} onRemoveFilter={onApply} />
    </aside>
  );
}

interface FilterSheetProps extends FilterPanelProps {
  /** Classes for the trigger, which sits in the toolbar pill. */
  triggerClassName?: string;
}

/** "Filter" button of the toolbar under 1001 px and the bottom sheet it opens (618:26974). */
export function FilterSheet({ triggerClassName, onApply, onReset, ...props }: FilterSheetProps) {
  const locale = getLocale();
  const [open, setOpen] = useState(false);
  const focusing = useFocusingHandlers({ onApply, onReset });
  const active = countActiveFilters(props.values);

  return (
    <Sheet
      open={open}
      onOpenChange={setOpen}
      title={t('catalog.filters.sheet_title', 'فلاتر متقدمة')}
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
          <Icon icon={ListFilter} size="sm" className="qb-tablet:size-5" />
          {active > 0 ? (
            <span className="inline-flex min-w-[18px] items-center justify-center rounded-qb-pill bg-qb-brand px-1 text-qb-tiny leading-[18px] font-semibold text-qb-on-brand">
              {formatNumber(active, locale)}
              <span className="sr-only"> {tPlural('catalog.filters.active_suffix', active)}</span>
            </span>
          ) : null}
        </button>
      }
    >
      <FilterPanel
        variant="sheet"
        {...props}
        onApply={(next) => {
          setOpen(false);
          focusing.onApply(next);
        }}
        onReset={() => {
          setOpen(false);
          focusing.onReset();
        }}
        onKeep={() => setOpen(false)}
      />
    </Sheet>
  );
}
