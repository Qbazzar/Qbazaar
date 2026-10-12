'use client';

import { CheckIcon } from '@/components/design-system/icons';
import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

export interface SelectCheckboxProps {
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
  /** Names the box for assistive tech ("Select All", "Select <title>"). */
  'aria-label': string;
  disabled?: boolean;
  className?: string;
}

/**
 * Selection box of the notifications and messages lists, as the reference
 * draws it: a 21 px r6 box with a 1.5 px #cbcbcb ring on white, orange with
 * the white 13 px tick inside when checked, and no transition. A button with
 * the checkbox role, so it is focusable and toggles with Space and Enter.
 */
export function SelectCheckbox({ checked, onCheckedChange, disabled, className, ...props }: SelectCheckboxProps) {
  return (
    <button
      type="button"
      role="checkbox"
      aria-checked={checked}
      aria-label={props['aria-label']}
      disabled={disabled}
      onClick={() => onCheckedChange(!checked)}
      className={cn(
        'flex size-[21px] shrink-0 cursor-pointer items-center justify-center rounded-qb-xs border-[1.5px] font-qb text-qb-body leading-[21px] text-qb-ink',
        checked ? 'border-qb-brand bg-qb-brand' : 'border-qb-acct-check-ring bg-qb-surface',
        'disabled:cursor-not-allowed disabled:opacity-50',
        focusRing,
        className,
      )}
    >
      {checked ? <CheckIcon aria-hidden="true" size={13} className="text-qb-on-brand" /> : null}
    </button>
  );
}
