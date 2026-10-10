'use client';

import { useState } from 'react';
import { Combobox } from '@base-ui/react/combobox';
import { DirectionProvider } from '@base-ui/react/direction-provider';
import { ChevronDown, Search } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { dirFor, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { selectFirstRowHint, selectPanel, selectRow, selectScrollbar } from '../dropdown';

export interface SelectOption {
  value: string;
  label: string;
  /** What the search box matches, when not the whole label (a district's own name). */
  searchText?: string;
}

interface SelectFieldProps {
  /** Id of the visible label that names the field. */
  labelledBy: string;
  options: SelectOption[];
  /** Options listed only once the visitor types, e.g. the districts under the cities. */
  searchOnlyOptions?: SelectOption[];
  value: string | null;
  onChange: (value: string) => void;
  /** Shown while nothing is chosen ("All Regions", "All Type"). */
  placeholder: string;
}

function matches(option: SelectOption, query: string, locale: string): boolean {
  return (option.searchText ?? option.label).toLocaleLowerCase(locale).includes(query.toLocaleLowerCase(locale));
}

/**
 * The designed select of the filters (264:4818): a 44 px field with a chevron
 * that opens a panel with a "Search.." box over the options. Base UI provides
 * the combobox and listbox roles, the arrow keys, Escape and outside-click
 * closing and the focus return. There is no "all" row: an applied choice is
 * cleared from its chip or "Reset All".
 */
export function SelectField({ labelledBy, options, searchOnlyOptions = [], value, onChange, placeholder }: SelectFieldProps) {
  const locale = getLocale();
  const [query, setQuery] = useState('');
  const trimmed = query.trim();
  const listed = trimmed ? [...options, ...searchOnlyOptions].filter((option) => matches(option, trimmed, locale)) : options;
  const selected = [...options, ...searchOnlyOptions].find((option) => option.value === value) ?? null;

  return (
    <DirectionProvider direction={dirFor(locale)}>
      <Combobox.Root
        items={[...options, ...searchOnlyOptions]}
        filteredItems={listed}
        value={selected}
        onValueChange={(next) => {
          if (next) onChange(next.value);
        }}
        inputValue={query}
        onInputValueChange={setQuery}
        onOpenChange={(open) => {
          if (!open) setQuery('');
        }}
        isItemEqualToValue={(item, current) => item.value === current.value}
      >
        <Combobox.Trigger
          aria-labelledby={labelledBy}
          className={cn(
            'group flex h-11 w-full cursor-pointer items-center justify-between gap-3 rounded-qb-lg border border-qb-line bg-qb-surface px-4 text-start font-qb text-qb-caption transition-colors',
            'hover:bg-qb-hover data-popup-open:bg-qb-surface disabled:cursor-not-allowed disabled:bg-qb-fill',
            focusRing,
          )}
        >
          <span className={cn('min-w-0 truncate', selected ? 'font-medium text-qb-ink' : 'text-qb-placeholder')}>
            {selected ? selected.label : placeholder}
          </span>
          <Icon
            icon={ChevronDown}
            className="size-[21px] shrink-0 text-qb-icon-faint transition-transform duration-200 group-data-popup-open:rotate-180 motion-reduce:transition-none"
          />
        </Combobox.Trigger>
        <Combobox.Portal>
          <Combobox.Positioner side="bottom" align="start" sideOffset={14} className="z-50">
            <Combobox.Popup className={selectPanel}>
              <div className="flex h-[42px] items-center gap-2.5 border-b border-(--color-qb-select-divider) px-4">
                <Icon icon={Search} className="size-[18px] shrink-0 text-(--color-qb-search-icon)" />
                <Combobox.Input
                  aria-label={t('catalog.filters.select_search', 'ابحث في الخيارات')}
                  placeholder={t('catalog.filters.select_search_placeholder', 'بحث..')}
                  className="h-full min-w-0 flex-1 bg-transparent font-qb text-qb-caption tracking-[-0.4px] text-qb-ink outline-none placeholder:text-(--color-qb-search-hint)"
                />
              </div>
              <Combobox.Empty className="px-4 py-3 text-qb-micro text-qb-ink-subtle empty:hidden">
                {t('catalog.filters.select_empty', 'لا توجد نتائج')}
              </Combobox.Empty>
              <Combobox.List className={cn('max-h-[166px] overflow-y-auto py-2.5 empty:hidden', selectScrollbar, selectFirstRowHint)}>
                {(option: SelectOption) => (
                  <Combobox.Item key={option.value} value={option} className={selectRow}>
                    {option.label}
                  </Combobox.Item>
                )}
              </Combobox.List>
            </Combobox.Popup>
          </Combobox.Positioner>
        </Combobox.Portal>
      </Combobox.Root>
    </DirectionProvider>
  );
}
