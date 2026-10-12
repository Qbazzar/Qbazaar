import '../catalog-tokens.css';

import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';

export interface FilterOption {
  value: string;
  label: string;
  /** Matching ads, from the search facets; zero is not shown. */
  count?: number;
}

interface OptionListProps {
  /** Shared `name` of the radio inputs; unique per panel. */
  name: string;
  labelledBy: string;
  options: FilterOption[];
  value: string | null;
  onChange: (value: string) => void;
  /** Classes for every option's label. */
  labelClassName?: string;
}

/**
 * The 19 px radio of 245:4549: a light ring, a dark ring on hover, an inner
 * ring while pressed, and an orange ring round an orange dot once chosen.
 */
const radio = [
  'size-[19px] shrink-0 cursor-pointer appearance-none rounded-full border border-(--color-qb-radio-ring) bg-qb-surface transition-[border-color,box-shadow] motion-reduce:transition-none',
  'not-checked:hover:border-(--color-qb-radio-ring-hover)',
  'not-checked:active:border-(--color-qb-radio-ring-hover) not-checked:active:shadow-[inset_0_0_0_1px_var(--color-qb-surface),inset_0_0_0_2px_var(--color-qb-radio-ring)]',
  'checked:border-qb-brand checked:bg-qb-brand checked:shadow-[inset_0_0_0_2px_var(--color-qb-surface)]',
  'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-qb-brand-active focus-visible:outline-solid',
].join(' ');

/** " (1,234)" after a label; nothing for a count that is missing or zero, so a timed-out count never reads as 0. */
function Count({ value }: { value: number | undefined }) {
  if (!value) return null;
  return <span className="font-normal tabular-nums"> ({formatNumber(value, getLocale())})</span>;
}

/**
 * Radio rows of a filter group ("Available (7,592,369)"). The design lists the
 * real options only; a chosen one is cleared from its chip or "Reset All".
 */
export function OptionList({ name, labelledBy, options, value, onChange, labelClassName }: OptionListProps) {
  return (
    <div role="radiogroup" aria-labelledby={labelledBy} className="flex flex-col gap-3">
      {options.map((option) => {
        const checked = value === option.value;
        return (
          <label key={option.value} className="flex cursor-pointer items-center gap-2 font-qb text-qb-body text-qb-ink-subtle">
            <input type="radio" name={name} value={option.value} checked={checked} onChange={() => onChange(option.value)} className={radio} />
            <span className={cn('min-w-0', labelClassName, checked && 'text-qb-ink-body')}>
              {option.label}
              <Count value={option.count} />
            </span>
          </label>
        );
      })}
    </div>
  );
}

interface CheckOptionProps {
  label: string;
  /** Matching ads, from the search facets; zero is not shown. */
  count?: number;
  checked: boolean;
  onChange: (checked: boolean) => void;
}

/** A single on / off row of a filter group ("Buy Now"), drawn like the radios of the other groups. */
export function CheckOption({ label, count, checked, onChange }: CheckOptionProps) {
  return (
    <label className="flex cursor-pointer items-center gap-2 font-qb text-qb-body text-qb-ink-subtle">
      <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className={radio} />
      <span className={cn('min-w-0', checked && 'text-qb-ink-body')}>
        {label}
        <Count value={count} />
      </span>
    </label>
  );
}
