'use client';

import { useMemo } from 'react';
import { CheckIcon, CircleIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { scorePassword } from '@/lib/validation/auth';
import { t } from '@/lib/i18n/messages';

export interface PasswordStrengthIndicatorProps {
  password: string;
  className?: string;
  /** Hide the per-rule checklist (used in tight layouts). */
  hideRules?: boolean;
}

const BAR_TONES: Record<0 | 1 | 2 | 3 | 4, string> = {
  0: 'bg-ink-200',
  1: 'bg-destructive',
  2: 'bg-coral',
  3: 'bg-coral',
  4: 'bg-sage',
};

export function PasswordStrengthIndicator({
  password,
  className,
  hideRules,
}: PasswordStrengthIndicatorProps) {
  const strength = useMemo(() => scorePassword(password), [password]);

  return (
    <div className={cn('space-y-2', className)} aria-live="polite">
      <div className="flex gap-1.5" role="img" aria-label={t(strength.labelKey)}>
        {[1, 2, 3, 4].map((seg) => (
          <span
            key={seg}
            className={cn(
              'h-1.5 flex-1 rounded-full transition-colors',
              seg <= strength.score ? BAR_TONES[strength.score] : 'bg-ink-200',
            )}
          />
        ))}
      </div>
      <div className="text-muted-foreground flex items-center justify-between text-xs">
        <span>{t('auth.password_strength.label')}</span>
        <span
          className={cn(
            'font-medium',
            strength.score >= 4 ? 'text-sage' : 'text-foreground',
          )}
        >
          {t(strength.labelKey)}
        </span>
      </div>
      {hideRules ? null : (
        <ul className="grid grid-cols-1 gap-1 text-xs sm:grid-cols-2">
          <Rule passed={strength.matched.length} labelKey="auth.password_strength.rules.length" />
          <Rule passed={strength.matched.uppercase} labelKey="auth.password_strength.rules.uppercase" />
          <Rule passed={strength.matched.lowercase} labelKey="auth.password_strength.rules.lowercase" />
          <Rule passed={strength.matched.number} labelKey="auth.password_strength.rules.number" />
          <Rule passed={strength.matched.symbol} labelKey="auth.password_strength.rules.symbol" />
        </ul>
      )}
    </div>
  );
}

function Rule({ passed, labelKey }: { passed: boolean; labelKey: string }) {
  return (
    <li
      className={cn(
        'flex items-center gap-2',
        passed ? 'text-sage' : 'text-muted-foreground',
      )}
    >
      {passed ? (
        <CheckIcon className="size-3.5" aria-hidden="true" />
      ) : (
        <CircleIcon className="size-3.5" aria-hidden="true" />
      )}
      <span>{t(labelKey)}</span>
    </li>
  );
}
