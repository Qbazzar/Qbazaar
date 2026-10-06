import { X } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import type { ActiveFilter } from './active-filters';
import type { FilterValues } from './filter-values';

interface ActiveFilterChipsProps {
  chips: ActiveFilter[];
  /** Applies the filters without the removed one. */
  onRemove: (next: FilterValues) => void;
}

/** One outlined chip per applied filter, each removing its filter at once (250:4405). */
export function ActiveFilterChips({ chips, onRemove }: ActiveFilterChipsProps) {
  if (!chips.length) return null;
  return (
    <ul aria-label={t('catalog.filters.applied', 'الفلاتر المطبّقة')} className="mb-[18px] flex flex-wrap gap-2.5">
      {chips.map((chip) => (
        <li key={chip.key}>
          <button
            type="button"
            onClick={() => onRemove(chip.without)}
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
