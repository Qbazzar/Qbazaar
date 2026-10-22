'use client';

import type { ReactNode } from 'react';

import '@/components/catalog/catalog-tokens.css';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { GridViewIcon, ListViewIcon } from '@/components/design-system/icons';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SortMode } from '@/lib/api/types';

import type { ViewMode } from './listing-query';
import { SortMenu } from './SortMenu';

interface ListingToolbarProps {
  /** `<FilterSheet>`; shown under 1001 px, where the sidebar is hidden. */
  filters?: ReactNode;
  sort?: { value: SortMode; onChange: (next: SortMode) => void };
  view?: { value: ViewMode; onChange: (next: ViewMode) => void };
  /** Page actions for phones (from 601 px they sit beside the title). */
  actions?: ReactNode;
}

/**
 * The row above the results (category.html): the Filter button and the sort
 * box side by side, 12 px apart, under 1001 px; the sort box and the two view
 * buttons on desktop. Phones end the row with the page actions as circles.
 */
export function ListingToolbar({ filters, sort, view, actions }: ListingToolbarProps) {
  return (
    <div className="flex items-center justify-between gap-3">
      <div className="flex min-w-0 items-center gap-3">
        {filters ? <div className="flex qb-desktop:hidden">{filters}</div> : null}
        {sort ? <SortMenu {...sort} /> : null}
      </div>
      <div className="flex items-center gap-2.5">
        {actions ? <div className="flex items-center gap-2.5 qb-tablet:hidden">{actions}</div> : null}
        {view ? <ViewToggle {...view} /> : null}
      </div>
    </div>
  );
}

const VIEW_ICONS = { list: ListViewIcon, grid: GridViewIcon } as const;
const VIEW_ORDER: ViewMode[] = ['list', 'grid'];

/**
 * Two separate 44 px `.qb-btn` squares, list first: the chosen view's icon is
 * dark, the other light grey. Only the other one dims under the pointer: the
 * chosen one is the reference's orange-styled button, whose hover polish.css
 * sets to no filter.
 */
function ViewToggle({ value, onChange }: { value: ViewMode; onChange: (next: ViewMode) => void }) {
  return (
    <div role="group" aria-label={t('catalog.view.label', 'طريقة العرض')} className="hidden gap-2 qb-desktop:flex">
      {VIEW_ORDER.map((mode) => (
        <button
          key={mode}
          type="button"
          aria-pressed={value === mode}
          aria-label={t(`catalog.view.${mode}`)}
          onClick={() => onChange(mode)}
          className={cn(
            'qb-motion inline-flex size-11 cursor-pointer items-center justify-center rounded-qb-lg border border-qb-line bg-qb-surface motion-reduce:transition-none',
            value === mode ? 'text-qb-ink' : 'text-(--color-qb-toggle-off) hover:brightness-[.96]',
            focusRing,
          )}
        >
          <Icon icon={VIEW_ICONS[mode]} size={20} />
        </button>
      ))}
    </div>
  );
}
