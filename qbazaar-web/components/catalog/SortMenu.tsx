'use client';

import { DirectionProvider } from '@base-ui/react/direction-provider';
import { Select } from '@base-ui/react/select';
import { ChevronDown } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
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
            'group flex h-full cursor-pointer items-center gap-2 rounded-qb-md ps-3 pe-2.5 font-qb text-qb-micro whitespace-nowrap text-qb-ink transition-colors',
            'hover:bg-qb-hover data-popup-open:bg-qb-hover',
            'qb-tablet:gap-2.5 qb-tablet:ps-4 qb-tablet:pe-3 qb-tablet:text-qb-body',
            'qb-desktop:min-w-[221px] qb-desktop:justify-between qb-desktop:rounded-qb-sm qb-desktop:ps-4 qb-desktop:pe-4 qb-desktop:text-qb-h5',
            focusRing,
          )}
        >
          <Select.Value />
          <Icon
            icon={ChevronDown}
            size="sm"
            className="transition-transform duration-200 group-data-popup-open:rotate-180 motion-reduce:transition-none qb-tablet:size-5 qb-desktop:size-6"
          />
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
