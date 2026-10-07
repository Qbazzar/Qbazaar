'use client';

import { Field } from '@/components/design-system/Field';
import { Input, Select } from '@/components/design-system/Input';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { CategoryField, CategoryFieldOption, CustomFieldsFilter } from '@/lib/api/types';

import { parseNonNegative } from './filter-values';
import { RangeFields } from './RangeFields';

interface CustomFieldFiltersProps {
  fields: CategoryField[];
  value: CustomFieldsFilter;
  onChange: (next: CustomFieldsFilter) => void;
}

type FieldValue = CustomFieldsFilter[string];

const control = 'h-11 rounded-qb-lg text-qb-caption';

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
 * schema ("Property Type: All Type", 264:4818).
 */
export function CustomFieldFilters({ fields, value, onChange }: CustomFieldFiltersProps) {
  const locale = getLocale();

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

        if (field.type === 'select') {
          return (
            <Field key={field.key} label={label}>
              {(props) => (
                <Select
                  {...props}
                  value={typeof current === 'string' ? current : ''}
                  onChange={(event) => setField(field.key, event.target.value || undefined)}
                  className={control}
                >
                  <option value="">{t('catalog.filters.any', 'الكل')}</option>
                  {selectOptions(field).map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              )}
            </Field>
          );
        }

        if (field.type === 'number') {
          const range = current && typeof current === 'object' ? current : {};
          return (
            <NumberRange
              key={field.key}
              label={label}
              min={range.min ?? null}
              max={range.max ?? null}
              onChange={(min, max) =>
                setField(field.key, min === null && max === null ? undefined : { ...(min !== null && { min }), ...(max !== null && { max }) })
              }
            />
          );
        }

        return (
          <Field key={field.key} label={label}>
            {(props) => (
              <Input
                {...props}
                value={typeof current === 'string' ? current : ''}
                onChange={(event) => setField(field.key, event.target.value || undefined)}
                className={control}
              />
            )}
          </Field>
        );
      })}
    </div>
  );
}

function NumberRange({
  label,
  min,
  max,
  onChange,
}: {
  label: string;
  min: number | null;
  max: number | null;
  onChange: (min: number | null, max: number | null) => void;
}) {
  return (
    <fieldset>
      <legend className="mb-2 font-qb text-qb-body text-qb-ink-body">{label}</legend>
      <RangeFields
        min={min}
        max={max}
        parse={parseNonNegative}
        onChange={onChange}
        minLabel={t('catalog.filters.range_min', { label }, '{label}: من')}
        maxLabel={t('catalog.filters.range_max', { label }, '{label}: إلى')}
        errorMessage={t('catalog.filters.range_error', 'لا يمكن أن يكون الحد الأعلى أقل من الحد الأدنى.')}
      />
    </fieldset>
  );
}
