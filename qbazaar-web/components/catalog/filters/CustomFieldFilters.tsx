'use client';

import { useId } from 'react';

import { Input } from '@/components/design-system/Input';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { CategoryField, CategoryFieldOption, CustomFieldsFilter } from '@/lib/api/types';

import { parseNonNegative } from './filter-values';
import { RangeFields } from './RangeFields';
import { SelectField } from './SelectField';

interface CustomFieldFiltersProps {
  fields: CategoryField[];
  value: CustomFieldsFilter;
  onChange: (next: CustomFieldsFilter) => void;
  /** The bottom sheet labels its fields smaller (618:26974). */
  compact?: boolean;
}

type FieldValue = CustomFieldsFilter[string];

/** The fields search can filter on: selects match a value, numbers a range, text an exact value. */
export function filterableFields(fields: CategoryField[] | null | undefined): CategoryField[] {
  return (fields ?? []).filter((field) => field.type === 'select' || field.type === 'number' || field.type === 'text');
}

/** A select's options with their label in the page language; the raw values when a field has no `options_labeled`. */
function selectOptions(field: CategoryField): CategoryFieldOption[] {
  return field.options_labeled ?? (field.options ?? []).map((value) => ({ value, label: value }));
}

/**
 * Category-specific filters built from the selected category's `custom_fields`
 * schema: designed selects ("Property Type: All Type", 264:4818), number
 * ranges and exact text values.
 */
export function CustomFieldFilters({ fields, value, onChange, compact = false }: CustomFieldFiltersProps) {
  const id = useId();
  const locale = getLocale();
  const labelClass = cn('block font-qb text-qb-ink-body', compact ? 'text-qb-caption font-medium' : 'text-qb-body');

  const setField = (key: string, next: FieldValue | undefined) => {
    const draft: CustomFieldsFilter = { ...value };
    if (next === undefined) delete draft[key];
    else draft[key] = next;
    onChange(draft);
  };

  return (
    <div className="flex flex-col gap-4">
      {filterableFields(fields).map((field) => {
        const label = localized(field.label, locale);
        const current = value[field.key];
        const labelId = `${id}-${field.key}`;

        if (field.type === 'select') {
          return (
            <div key={field.key} className="flex flex-col gap-2">
              <span id={labelId} className={labelClass}>
                {label}
              </span>
              <SelectField
                labelledBy={labelId}
                options={selectOptions(field)}
                value={typeof current === 'string' ? current : null}
                onChange={(next) => setField(field.key, next)}
                placeholder={t('catalog.filters.all_type', 'كل الأنواع')}
              />
            </div>
          );
        }

        if (field.type === 'number') {
          const range = current && typeof current === 'object' ? current : {};
          return (
            <fieldset key={field.key}>
              <legend className={cn(labelClass, 'mb-2')}>{label}</legend>
              <RangeFields
                min={range.min ?? null}
                max={range.max ?? null}
                parse={parseNonNegative}
                onChange={(min, max) =>
                  setField(field.key, min === null && max === null ? undefined : { ...(min !== null && { min }), ...(max !== null && { max }) })
                }
                minLabel={t('catalog.filters.range_min', { label }, '{label}: من')}
                maxLabel={t('catalog.filters.range_max', { label }, '{label}: إلى')}
                errorMessage={t('catalog.filters.range_error', 'لا يمكن أن يكون الحد الأعلى أقل من الحد الأدنى.')}
              />
            </fieldset>
          );
        }

        return (
          <div key={field.key} className="flex flex-col gap-2">
            <label htmlFor={labelId} className={labelClass}>
              {label}
            </label>
            <Input
              id={labelId}
              value={typeof current === 'string' ? current : ''}
              onChange={(event) => setField(field.key, event.target.value || undefined)}
              className="h-11 rounded-qb-lg text-qb-caption"
            />
          </div>
        );
      })}
    </div>
  );
}
