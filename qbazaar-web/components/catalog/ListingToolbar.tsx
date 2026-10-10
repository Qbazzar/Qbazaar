'use client';

import type { ReactNode } from 'react';
import { LayoutGrid, List } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SortMode } from '@/lib/api/types';

import { toolbarPill } from './layout';
import type { ViewMode } from './listing-query';
import { SortMenu } from './SortMenu';

interface ListingToolbarProps {
  /** `<FilterSheet>`; shown under 1001 px, where the sidebar is hidden. */
  filters?: ReactNode;
  sort?: { value: SortMode; onChange: (next: SortMode) => void };
  view?: { value: ViewMode; onChange: (next: ViewMode) => void };
  /** Page actions for tablets and phones (desktop shows them in the header). */
  actions?: ReactNode;
}

/**
 * Row above the results: "Filter | Newest" pill and the page actions under
 * 1001 px (544:38513, 623:30012); sort pill and grid/list toggle on desktop
 * (69:467).
 */
export function ListingToolbar({ filters, sort, view, actions }: ListingToolbarProps) {
  return (
    <div className="flex items-center justify-between gap-3">
      {filters || sort ? (
        <div className={cn(toolbarPill, 'h-10 qb-tablet:h-11 qb-desktop:h-14 qb-desktop:rounded-qb-sm', !sort && 'qb-desktop:hidden')}>
          {filters ? <div className="flex h-full qb-desktop:hidden">{filters}</div> : null}
          {filters && sort ? <span aria-hidden="true" className="w-px self-stretch bg-qb-line qb-desktop:hidden" /> : null}
          {sort ? <SortMenu {...sort} /> : null}
        </div>
      ) : (
        <span />
      )}
      <div className="flex items-center gap-2.5">
        {actions ? <div className="flex items-center gap-2.5 qb-desktop:hidden">{actions}</div> : null}
        {view ? <ViewToggle {...view} /> : null}
      </div>
    </div>
  );
}

const VIEW_ICONS = { grid: LayoutGrid, list: List } as const;
const VIEW_ORDER: ViewMode[] = ['grid', 'list'];

function ViewToggle({ value, onChange }: { value: ViewMode; onChange: (next: ViewMode) => void }) {
  return (
    <div role="group" aria-label={t('catalog.view.label', 'طريقة العرض')} className={cn(toolbarPill, 'hidden h-14 gap-[3px] p-[5px] qb-desktop:flex')}>
      {VIEW_ORDER.map((mode) => (
        <button
          key={mode}
          type="button"
          aria-pressed={value === mode}
          aria-label={t(`catalog.view.${mode}`)}
          onClick={() => onChange(mode)}
          className={cn(
            'inline-flex h-11 w-[49px] cursor-pointer items-center justify-center rounded-qb-md transition-colors',
            value === mode ? 'bg-qb-fill text-qb-ink' : 'text-qb-ink-disabled hover:text-qb-ink-muted',
            focusRing,
          )}
        >
          <Icon icon={VIEW_ICONS[mode]} size="lg" />
        </button>
      ))}
    </div>
  );
}
