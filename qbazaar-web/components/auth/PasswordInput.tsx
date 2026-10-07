'use client';

import { useState, type Ref } from 'react';
import { Eye, EyeOff } from 'lucide-react';

import { Input, type InputProps } from '@/components/design-system/Input';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export interface PasswordInputProps extends Omit<InputProps, 'type' | 'startIcon'> {
  ref?: Ref<HTMLInputElement>;
}

/**
 * Design-system input with the show/hide eye of the auth and settings forms.
 * Passwords read left to right in both languages, so the field stays LTR.
 */
export function PasswordInput({ className, ...props }: PasswordInputProps) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="relative" dir="ltr">
      <Input type={visible ? 'text' : 'password'} className={cn('pe-12', className)} {...props} />
      <button
        type="button"
        onClick={() => setVisible((value) => !value)}
        aria-label={t('auth.password.toggle')}
        aria-pressed={visible}
        aria-controls={props.id}
        className={cn(
          'absolute inset-y-0 end-0 flex w-12 items-center justify-center rounded-e-qb-md text-qb-ink-subtle hover:text-qb-ink',
          focusRing,
        )}
      >
        <Icon icon={visible ? EyeOff : Eye} />
      </button>
    </div>
  );
}
