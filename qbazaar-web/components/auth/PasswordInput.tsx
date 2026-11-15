'use client';

import { useState, type Ref } from 'react';

import { EyeOffIcon } from '@/components/design-system/icons';
import { Input, type InputProps } from '@/components/design-system/Input';
import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { authFocusClass, authPlaceholderClass } from './AuthFooter';

export interface PasswordInputProps extends Omit<InputProps, 'type' | 'startIcon'> {
  ref?: Ref<HTMLInputElement>;
  /** The auth pages' #BFBFBF placeholder; everywhere else the standard placeholder stays. */
  auth?: boolean;
}

/**
 * Design-system input with the `.qb-eye` toggle of the auth and settings
 * forms (auth.css, auth.js): a bare 20 px slashed eye 14 px from the inline
 * end (on the left in Arabic) that only switches the field's type, so the
 * glyph stays the same and `aria-pressed` carries the state. The field keeps
 * the 16 px padding of `.qb-field input` on both sides, as the design does, and
 * the typed password always reads left to right.
 */
export function PasswordInput({ className, auth = false, ...props }: PasswordInputProps) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="relative">
      <Input
        type={visible ? 'text' : 'password'}
        dir="ltr"
        className={cn(authFocusClass, auth && authPlaceholderClass, 'rtl:placeholder:text-right', className)}
        {...props}
      />
      <button
        type="button"
        onClick={() => setVisible((value) => !value)}
        aria-label={t('auth.password.toggle')}
        aria-pressed={visible}
        aria-controls={props.id}
        className={cn(
          // A 24 px box (the WCAG 2.2 minimum target) with the 20 px glyph centred, so the glyph keeps the
          // design's 14 px inset; the pseudo-element still widens the pointer target to 36 px.
          'absolute end-3 top-1/2 flex size-6 -translate-y-1/2 cursor-pointer items-center justify-center text-qb-auth-eye',
          "before:absolute before:-inset-1.5 before:content-['']",
          focusRing,
        )}
      >
        <EyeOffIcon aria-hidden="true" className="size-5" />
      </button>
    </div>
  );
}
