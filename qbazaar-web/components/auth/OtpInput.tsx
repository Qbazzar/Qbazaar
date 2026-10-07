'use client';

import { useCallback, useEffect, useId, useMemo, useRef } from 'react';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

/**
 * 6-box OTP input.
 *
 * Behaviour:
 *  - Auto-advances to the next box as soon as a digit is typed.
 *  - Backspace on an empty box jumps to the previous box and clears it.
 *  - ArrowLeft / ArrowRight move focus through the boxes (always in LTR order,
 *    even inside an RTL layout — the code itself is a number, not language).
 *  - Pasting a 6-digit string anywhere fills all boxes and triggers `onComplete`.
 *  - When the 6th digit is entered the parent's `onComplete` callback fires so
 *    the form can auto-submit. The submit button stays available for a11y.
 *
 * The component is a controlled input: `value` is the current 6-char string
 * (may be shorter while being typed), and `onChange` always emits the new
 * sanitised string.
 */
export interface OtpInputProps {
  value: string;
  onChange: (next: string) => void;
  onComplete?: (code: string) => void;
  length?: number;
  disabled?: boolean;
  ariaInvalid?: boolean;
  ariaDescribedBy?: string;
  ariaLabel?: string;
  autoFocus?: boolean;
  className?: string;
}

const DIGIT_REGEX = /^[0-9]$/;

function sanitiseDigits(value: string, max: number): string {
  return value.replace(/\D/g, '').slice(0, max);
}

export function OtpInput({
  value,
  onChange,
  onComplete,
  length = 6,
  disabled,
  ariaInvalid,
  ariaDescribedBy,
  ariaLabel,
  autoFocus,
  className,
}: OtpInputProps) {
  const groupId = useId();
  const inputsRef = useRef<Array<HTMLInputElement | null>>([]);
  // `digits` is always exactly `length` long, padded with empty strings.
  const digits = useMemo(() => {
    const sanitised = sanitiseDigits(value, length);
    return Array.from({ length }, (_, i) => sanitised[i] ?? '');
  }, [value, length]);

  // Notify parent only when the boxes hold a full code. We don't want to
  // call onComplete on every keystroke so we guard with the current value.
  useEffect(() => {
    if (value.length === length && onComplete) {
      onComplete(value);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, length]);

  useEffect(() => {
    if (autoFocus) {
      // Focus the first empty box (or the first box if all empty).
      const first = digits.findIndex((d) => d === '');
      const index = first === -1 ? 0 : first;
      inputsRef.current[index]?.focus();
    }
    // We only want to auto-focus on mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const updateAt = useCallback(
    (index: number, digit: string) => {
      const next = [...digits];
      next[index] = digit;
      onChange(next.join(''));
    },
    [digits, onChange],
  );

  const focusBox = useCallback((index: number) => {
    const clamped = Math.max(0, Math.min(index, length - 1));
    inputsRef.current[clamped]?.focus();
    inputsRef.current[clamped]?.select();
  }, [length]);

  const handleChange = (index: number, raw: string) => {
    // The input might receive a paste-like multi-char value (mobile autofill).
    const digitsOnly = sanitiseDigits(raw, length);

    if (digitsOnly.length === 0) {
      updateAt(index, '');
      return;
    }

    if (digitsOnly.length === 1) {
      updateAt(index, digitsOnly);
      if (index < length - 1) focusBox(index + 1);
      return;
    }

    // Multi-digit input: spread it across the remaining boxes starting from
    // the current index. Common when iOS pastes the full SMS code into one box.
    const merged = [...digits];
    let cursor = index;
    for (const ch of digitsOnly) {
      if (cursor >= length) break;
      merged[cursor] = ch;
      cursor += 1;
    }
    onChange(merged.join(''));
    focusBox(Math.min(cursor, length - 1));
  };

  const handleKeyDown = (
    index: number,
    event: React.KeyboardEvent<HTMLInputElement>,
  ) => {
    const key = event.key;
    if (key === 'Backspace') {
      if (digits[index]) {
        updateAt(index, '');
        return;
      }
      if (index > 0) {
        event.preventDefault();
        updateAt(index - 1, '');
        focusBox(index - 1);
      }
      return;
    }
    if (key === 'ArrowLeft') {
      event.preventDefault();
      focusBox(index - 1);
      return;
    }
    if (key === 'ArrowRight') {
      event.preventDefault();
      focusBox(index + 1);
      return;
    }
    if (key === 'Home') {
      event.preventDefault();
      focusBox(0);
      return;
    }
    if (key === 'End') {
      event.preventDefault();
      focusBox(length - 1);
      return;
    }
    // Block non-digit single-character keys so the input never visually drifts.
    if (key.length === 1 && !DIGIT_REGEX.test(key)) {
      event.preventDefault();
    }
  };

  const handlePaste = (
    index: number,
    event: React.ClipboardEvent<HTMLInputElement>,
  ) => {
    const pasted = sanitiseDigits(
      event.clipboardData.getData('text'),
      length,
    );
    if (!pasted) return;
    event.preventDefault();
    const merged = [...digits];
    let cursor = index;
    for (const ch of pasted) {
      if (cursor >= length) break;
      merged[cursor] = ch;
      cursor += 1;
    }
    onChange(merged.join(''));
    focusBox(Math.min(cursor, length - 1));
  };

  return (
    <div
      role="group"
      aria-label={ariaLabel ?? t('auth.verify_otp.code_label')}
      aria-describedby={ariaDescribedBy}
      className={cn('flex w-full items-center justify-center gap-2 font-qb qb-tablet:gap-3 qb-desktop:gap-3.5', className)}
      // OTP digits read left-to-right regardless of page direction.
      dir="ltr"
    >
      {digits.map((digit, index) => (
        <span
          key={`${groupId}-${index}`}
          className="relative flex h-[52px] w-full max-w-12 min-w-0 flex-1 qb-tablet:h-16 qb-tablet:max-w-[74px] qb-desktop:h-[57px] qb-desktop:max-w-[93px]"
        >
          <input
            ref={(el) => {
              inputsRef.current[index] = el;
            }}
            type="text"
            inputMode="numeric"
            autoComplete={index === 0 ? 'one-time-code' : 'off'}
            maxLength={1}
            value={digit}
            onChange={(event) => handleChange(index, event.target.value)}
            onKeyDown={(event) => handleKeyDown(index, event)}
            onPaste={(event) => handlePaste(index, event)}
            onFocus={(event) => event.currentTarget.select()}
            disabled={disabled}
            aria-label={t('auth.verify_otp.code_aria_box').replace(
              '{index}',
              String(index + 1),
            )}
            aria-invalid={ariaInvalid}
            data-otp-box={index}
            className={cn(
              'peer size-full min-w-0 rounded-qb-md border border-qb-line bg-qb-surface text-center text-qb-h5 font-medium text-qb-ink outline-none transition-colors',
              'qb-tablet:rounded-qb-lg qb-tablet:text-qb-h3 qb-desktop:text-qb-h5',
              'focus-visible:border-qb-brand focus-visible:ring-2 focus-visible:ring-qb-brand/20',
              'disabled:pointer-events-none disabled:opacity-50',
              'aria-invalid:border-qb-danger aria-invalid:ring-2 aria-invalid:ring-qb-danger/20',
            )}
          />
          {/* The faint "0" of the design's empty boxes; a placeholder would be read out as if a digit were filled in. */}
          {digit ? null : (
            <span
              aria-hidden="true"
              className="pointer-events-none absolute inset-0 flex items-center justify-center text-qb-h5 font-medium text-qb-line peer-disabled:opacity-50 qb-tablet:text-qb-h3 qb-desktop:text-qb-h5"
            >
              0
            </span>
          )}
        </span>
      ))}
    </div>
  );
}
