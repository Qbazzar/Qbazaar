'use client';

/**
 * Category-specific filters, driven by the selected category's `custom_fields`
 * schema. Renders a control per filterable field:
 *   - select → dropdown (equality)
 *   - number → min/max range
 *   - text   → single equality input
 * Boolean/date fields are skipped for now. Values bubble up as the
 * `CustomFieldsFilter` shape the search API expects.
 */
import { useEffect, useState } from 'react';
import type { CategoryField, CustomFieldsFilter } from '@/lib/api/types';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

const inputClass =
  'border-input bg-card text-ink-900 focus-visible:ring-ring/50 focus-visible:border-ring h-9 w-full rounded-lg border px-3 text-sm transition-colors outline-none focus-visible:ring-3';

interface Props {
  fields: CategoryField[];
  value: CustomFieldsFilter;
  onChange: (next: CustomFieldsFilter) => void;
}

export function CustomFieldFilters({ fields, value, onChange }: Props) {
  const locale = getLocale();
  const filterable = fields.filter(
    (f) => f.type === 'select' || f.type === 'number' || f.type === 'text',
  );

  if (filterable.length === 0) return null;

  const setField = (key: string, next: CustomFieldsFilter[string] | undefined) => {
    const draft: CustomFieldsFilter = { ...value };
    if (next === undefined) {
      delete draft[key];
    } else {
      draft[key] = next;
    }
    onChange(draft);
  };

  return (
    <div className="space-y-4">
      {filterable.map((field) => {
        const label = localized(field.label, locale);
        const current = value[field.key];

        if (field.type === 'select') {
          const selected = typeof current === 'string' ? current : '';
          return (
            <label key={field.key} className="block space-y-1">
              <span className="text-ink-700 block text-xs font-medium">
                {label}
              </span>
              <select
                value={selected}
                onChange={(e) =>
                  setField(field.key, e.target.value || undefined)
                }
                className={inputClass}
              >
                <option value="">{t('search.facets.any', 'الكل')}</option>
                {(field.options ?? []).map((opt) => (
                  <option key={opt} value={opt}>
                    {opt}
                  </option>
                ))}
              </select>
            </label>
          );
        }

        if (field.type === 'number') {
          const range =
            current && typeof current === 'object' ? current : {};
          return (
            <NumberRangeField
              key={field.key}
              label={label}
              min={range.min ?? null}
              max={range.max ?? null}
              onCommit={(min, max) =>
                setField(
                  field.key,
                  min === null && max === null
                    ? undefined
                    : {
                        ...(min !== null ? { min } : {}),
                        ...(max !== null ? { max } : {}),
                      },
                )
              }
            />
          );
        }

        // text → equality
        const textVal = typeof current === 'string' ? current : '';
        return (
          <TextField
            key={field.key}
            label={label}
            value={textVal}
            onCommit={(v) => setField(field.key, v || undefined)}
          />
        );
      })}
    </div>
  );
}

function NumberRangeField({
  label,
  min,
  max,
  onCommit,
}: {
  label: string;
  min: number | null;
  max: number | null;
  onCommit: (min: number | null, max: number | null) => void;
}) {
  const [localMin, setLocalMin] = useState(min === null ? '' : String(min));
  const [localMax, setLocalMax] = useState(max === null ? '' : String(max));

  useEffect(() => setLocalMin(min === null ? '' : String(min)), [min]);
  useEffect(() => setLocalMax(max === null ? '' : String(max)), [max]);

  const parse = (raw: string): number | null => {
    if (raw === '') return null;
    const n = Number(raw);
    return Number.isFinite(n) ? n : null;
  };

  const commit = () => onCommit(parse(localMin), parse(localMax));

  return (
    <div className="space-y-1">
      <span className="text-ink-700 block text-xs font-medium">{label}</span>
      <div className="grid grid-cols-2 gap-2">
        <input
          type="number"
          inputMode="numeric"
          value={localMin}
          onChange={(e) => setLocalMin(e.target.value)}
          onBlur={commit}
          onKeyDown={(e) => e.key === 'Enter' && commit()}
          className={inputClass}
          placeholder={t('search.facets.price_min', 'الحد الأدنى')}
        />
        <input
          type="number"
          inputMode="numeric"
          value={localMax}
          onChange={(e) => setLocalMax(e.target.value)}
          onBlur={commit}
          onKeyDown={(e) => e.key === 'Enter' && commit()}
          className={inputClass}
          placeholder={t('search.facets.price_max', 'الحد الأعلى')}
        />
      </div>
    </div>
  );
}

function TextField({
  label,
  value,
  onCommit,
}: {
  label: string;
  value: string;
  onCommit: (value: string) => void;
}) {
  const [local, setLocal] = useState(value);
  useEffect(() => setLocal(value), [value]);

  return (
    <label className="block space-y-1">
      <span className="text-ink-700 block text-xs font-medium">{label}</span>
      <input
        type="text"
        value={local}
        onChange={(e) => setLocal(e.target.value)}
        onBlur={() => onCommit(local.trim())}
        onKeyDown={(e) => e.key === 'Enter' && onCommit(local.trim())}
        className={inputClass}
      />
    </label>
  );
}
