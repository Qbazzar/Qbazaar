'use client';

import { useEffect, useRef } from 'react';
import { X } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { useRequestResultsFocus } from '../results-focus';

import type { ActiveFilter } from './active-filters';
import type { FilterValues } from './filter-values';

interface ActiveFilterChipsProps {
  chips: ActiveFilter[];
  /** Applies the filters without the removed one. */
  onRemove: (next: FilterValues) => void;
  /** Moves the focus to the next chip, or to the results after the last one, once the filters apply. */
  focusNextAfterRemove?: boolean;
}

/** One outlined chip per applied filter, each removing its filter at once (250:4405). */
export function ActiveFilterChips({ chips, onRemove, focusNextAfterRemove = false }: ActiveFilterChipsProps) {
  const listRef = useRef<HTMLUListElement>(null);
  const focusIndex = useRef<number | null>(null);
  const requestResultsFocus = useRequestResultsFocus();
  const chipKeys = chips.map((chip) => chip.key).join('|');

  useEffect(() => {
    const index = focusIndex.current;
    const buttons = listRef.current?.querySelectorAll('button');
    if (index === null || !buttons?.length) return;
    focusIndex.current = null;
    buttons[Math.min(index, buttons.length - 1)].focus();
  }, [chipKeys]);

  if (!chips.length) return null;

  const remove = (index: number) => {
    if (focusNextAfterRemove) {
      if (chips.length > 1) focusIndex.current = index;
      else requestResultsFocus();
    }
    onRemove(chips[index].without);
  };

  return (
    <ul ref={listRef} aria-label={t('catalog.filters.applied', 'الفلاتر المطبّقة')} className="mb-[18px] flex flex-wrap gap-2.5">
      {chips.map((chip, index) => (
        <li key={chip.key}>
          <button
            type="button"
            onClick={() => remove(index)}
            aria-label={t('catalog.filters.remove', { label: chip.label }, 'إزالة {label}')}
            className={cn(
              'inline-flex min-h-10 cursor-pointer items-center gap-3 rounded-qb-sm border border-qb-brand bg-qb-brand-soft px-3.5 font-qb text-qb-caption text-qb-brand-on-soft transition-colors hover:border-qb-brand-active hover:text-qb-brand-active',
              focusRing,
            )}
          >
            {chip.label}
            <Icon icon={X} size="sm" />
          </button>
        </li>
      ))}
    </ul>
  );
}
