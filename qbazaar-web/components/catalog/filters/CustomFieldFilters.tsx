'use client';

import { useId } from 'react';

import { Field } from '@/components/design-system/Field';
import { Input, Select } from '@/components/design-system/Input';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { CategoryField, CustomFieldsFilter } from '@/lib/api/types';

import { parseNonNegative } from './filter-values';

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
                  {(field.options ?? []).map((option) => (
                    <option key={option} value={option}>
                      {option}
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
  const id = useId();
  return (
    <fieldset>
      <legend className="mb-2 font-qb text-qb-body text-qb-ink-body">{label}</legend>
      <div className="flex items-center gap-3">
        <label htmlFor={`${id}-min`} className="sr-only">
          {t('catalog.filters.range_min', { label }, '{label}: من')}
        </label>
        <Input
          id={`${id}-min`}
          type="number"
          inputMode="numeric"
          placeholder={t('common.min', 'الأدنى')}
          value={min ?? ''}
          onChange={(event) => onChange(parseNonNegative(event.target.value), max)}
          className={control}
        />
        <span aria-hidden="true" className="text-qb-caption text-qb-ink-muted">
          -
        </span>
        <label htmlFor={`${id}-max`} className="sr-only">
          {t('catalog.filters.range_max', { label }, '{label}: إلى')}
        </label>
        <Input
          id={`${id}-max`}
          type="number"
          inputMode="numeric"
          placeholder={t('common.max', 'الأعلى')}
          value={max ?? ''}
          onChange={(event) => onChange(min, parseNonNegative(event.target.value))}
          className={control}
        />
      </div>
    </fieldset>
  );
}
