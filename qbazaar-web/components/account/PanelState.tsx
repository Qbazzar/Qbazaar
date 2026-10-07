import { Loader2 } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export interface PanelStateProps {
  loading: boolean;
  /** Shown when not loading; defaults to the network error. */
  message?: string;
  className?: string;
}

/** Spinner while an account list loads, or its error line. */
export function PanelState({ loading, message, className }: PanelStateProps) {
  if (loading) {
    return (
      <div role="status" className={cn('flex justify-center py-12', className)}>
        <Loader2 className="size-6 animate-spin text-qb-ink-subtle motion-reduce:animate-none" aria-hidden="true" />
        <span className="sr-only">{t('common.loading')}</span>
      </div>
    );
  }
  return (
    <p role="alert" className={cn('py-12 text-center font-qb text-qb-caption text-qb-danger', className)}>
      {message ?? t('auth.errors.network')}
    </p>
  );
}
