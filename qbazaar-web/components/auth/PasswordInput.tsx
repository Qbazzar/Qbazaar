'use client';

import { useState, type Ref } from 'react';

import { EyeOffIcon } from '@/components/design-system/icons';
import { Input, type InputProps } from '@/components/design-system/Input';
import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { authInputClass } from './AuthFooter';

export interface PasswordInputProps extends Omit<InputProps, 'type' | 'startIcon'> {
  ref?: Ref<HTMLInputElement>;
}

/**
 * Design-system input with the `.qb-eye` toggle of the auth and settings
 * forms (auth.css, auth.js): a bare 20 px slashed eye 14 px from the inline
 * end (on the left in Arabic) that only switches the field's type, so the
 * glyph stays the same and `aria-pressed` carries the state. The field keeps
 * the 16 px padding of `.qb-field input` on both sides, as the design does, and
 * the typed password always reads left to right.
 */
export function PasswordInput({ className, ...props }: PasswordInputProps) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="relative">
      <Input
        type={visible ? 'text' : 'password'}
        dir="ltr"
        className={cn(authInputClass, 'rtl:placeholder:text-right', className)}
        {...props}
      />
      <button
        type="button"
        onClick={() => setVisible((value) => !value)}
        aria-label={t('auth.password.toggle')}
        aria-pressed={visible}
        aria-controls={props.id}
        className={cn(
          'absolute end-3.5 top-1/2 flex -translate-y-1/2 cursor-pointer text-qb-auth-eye',
          // The 20 px glyph is the box; this keeps a 36 px pointer target around it.
          "before:absolute before:-inset-2 before:content-['']",
          focusRing,
        )}
      >
        <EyeOffIcon aria-hidden="true" className="size-5" />
      </button>
    </div>
  );
}
