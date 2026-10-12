'use client';

import { useState } from 'react';

import '../catalog-tokens.css';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { FilterLinesIcon, FunnelIcon } from '@/components/design-system/icons';
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
      className="hidden w-[360px] shrink-0 self-start rounded-qb-xl border border-qb-line bg-qb-surface p-6 shadow-(--shadow-qb-pill) qb-desktop:block"
    >
      {/* No focus request for the chips: a removed chip hands the focus to the next one. */}
      <FilterPanel variant="sidebar" {...props} {...focusing} onRemoveFilter={onApply} />
    </aside>
  );
}

/**
 * The sheet itself does not scroll: its handle, title and close button stay on
 * top while the filter form scrolls under them (618:26974, 244:3508). The title
 * takes the sheet's text colour; the form sets its own. The card is the
 * reference's sheet (category.html aside on phones): 22 px top corners, a
 * hairline and the upward 44 px shadow.
 */
const sheetLayout =
  'overflow-hidden rounded-t-[22px] border border-qb-line pb-0 shadow-qb-sheet text-(--color-qb-sheet-title) *:last:flex *:last:min-h-0 *:last:flex-col';

/**
 * The reference's two "Filter" buttons under 1001 px:
 * - `pill`: the listing's `.qb-filter-btn` (category.html): funnel first, 600, a soft shadow, #fafafa under the pointer.
 * - `row`: the parent category's `.qb-cp-row .btn` (parent-category.html): text first, the filter lines after it, no hover.
 */
const TRIGGERS = {
  pill: {
    button:
      'gap-2 rounded-qb-md px-[18px] py-2.5 text-qb-label leading-normal font-semibold text-qb-ink-title shadow-(--shadow-qb-filter-btn) qb-motion hover:bg-qb-hover motion-reduce:transition-none',
    icon: FunnelIcon,
    iconSize: 15,
    iconFirst: true,
  },
  row: {
    button: 'gap-2 rounded-qb-lg px-4 py-[11px] text-qb-caption leading-normal font-medium text-qb-ink',
    icon: FilterLinesIcon,
    iconSize: 16,
    iconFirst: false,
  },
} as const;

interface FilterSheetProps extends FilterPanelProps {
  /** Which of the reference's two Filter buttons opens the sheet. */
  trigger?: keyof typeof TRIGGERS;
}

/** "Filter" button of the toolbar under 1001 px and the bottom sheet it opens (618:26974). */
export function FilterSheet({ trigger = 'pill', onApply, onReset, ...props }: FilterSheetProps) {
  const locale = getLocale();
  const look = TRIGGERS[trigger];
  const icon = <Icon icon={look.icon} size={look.iconSize} />;
  const [open, setOpen] = useState(false);
  const focusing = useFocusingHandlers({ onApply, onReset });
  const active = countActiveFilters(props.values, { keyword: props.keywordIsFilter });

  return (
    <Sheet
      open={open}
      onOpenChange={setOpen}
      title={t('catalog.filters.sheet_title', 'فلاتر متقدمة')}
      className={cn(headingFont, sheetLayout)}
      trigger={
        <button
          type="button"
          className={cn('inline-flex shrink-0 cursor-pointer items-center border border-qb-line bg-qb-surface font-qb whitespace-nowrap', look.button, focusRing)}
        >
          {look.iconFirst ? icon : null}
          <span>{t('catalog.filters.open', 'فلتر')}</span>
          {look.iconFirst ? null : icon}
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
