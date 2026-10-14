'use client';

import { DirectionProvider } from '@base-ui/react/direction-provider';
import { Select } from '@base-ui/react/select';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { ChevronDownIcon } from '@/components/design-system/icons';
import { dirFor, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SortMode } from '@/lib/api/types';

import { sortPanel, sortRow } from './dropdown';
import { SORT_MODES } from './listing-query';

interface SortMenuProps {
  value: SortMode;
  onChange: (next: SortMode) => void;
}

/**
 * The "Newest" sort control: a designed dropdown, not the platform menu
 * (259:5246, flow 69:467 → 260:4670). Base UI gives it the listbox roles,
 * arrow keys and type-ahead, Escape and outside-click closing, and returns the
 * focus to the trigger.
 */
export function SortMenu({ value, onChange }: SortMenuProps) {
  const items = SORT_MODES.map((mode) => ({ value: mode, label: t(`catalog.sort.${mode}`) }));

  return (
    <DirectionProvider direction={dirFor(getLocale())}>
      <Select.Root items={items} value={value} onValueChange={(next) => next && onChange(next)} modal={false}>
        <Select.Label className="sr-only">{t('search.sort.label', 'الترتيب')}</Select.Label>
        <Select.Trigger
          className={cn(
            // The reference's sort box: 15 px, 20 px from 1001 px (catListView), no hover of its own.
            'group flex cursor-pointer items-center gap-2 rounded-qb-lg border border-qb-line bg-qb-surface px-4 py-[11px] font-qb text-qb-body-sm leading-normal whitespace-nowrap text-qb-ink-body qb-desktop:text-qb-h5',
            focusRing,
          )}
        >
          <Select.Value />
          <Icon icon={ChevronDownIcon} size={18} className="transition-transform duration-200 group-data-popup-open:rotate-180 motion-reduce:transition-none" />
        </Select.Trigger>
        <Select.Portal>
          <Select.Positioner alignItemWithTrigger={false} side="bottom" align="start" sideOffset={14} className="z-50">
            <Select.Popup className={sortPanel}>
              <Select.List>
                {items.map((item) => (
                  <Select.Item key={item.value} value={item.value} className={sortRow}>
                    <Select.ItemText>{item.label}</Select.ItemText>
                  </Select.Item>
                ))}
              </Select.List>
            </Select.Popup>
          </Select.Positioner>
        </Select.Portal>
      </Select.Root>
    </DirectionProvider>
  );
}
