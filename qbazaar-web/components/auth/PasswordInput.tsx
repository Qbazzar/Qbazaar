'use client';

import { useState, type Ref } from 'react';
import { Eye, EyeOff } from 'lucide-react';

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
 * forms: the slashed eye while the password is hidden, the open eye while it
 * shows. The eye sits at the inline end (on the left in Arabic); the typed
 * password itself always reads left to right.
 */
export function PasswordInput({ className, ...props }: PasswordInputProps) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="relative">
      <Input
        type={visible ? 'text' : 'password'}
        dir="ltr"
        className={cn(authInputClass, 'pr-12 rtl:pr-4 rtl:pl-12 rtl:placeholder:text-right', className)}
        {...props}
      />
      <button
        type="button"
        onClick={() => setVisible((value) => !value)}
        aria-label={t('auth.password.toggle')}
        aria-pressed={visible}
        aria-controls={props.id}
        className={cn(
          'absolute inset-y-0 end-0 flex w-12 cursor-pointer items-center justify-center rounded-e-qb-md text-qb-auth-eye',
          focusRing,
        )}
      >
        {visible ? (
          <Eye aria-hidden="true" className="size-5" strokeWidth={1.6} />
        ) : (
          <EyeOff aria-hidden="true" className="size-5" strokeWidth={1.6} />
        )}
      </button>
    </div>
  );
}
