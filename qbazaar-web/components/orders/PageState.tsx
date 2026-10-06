import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { t } from '@/lib/i18n/messages';

export type PageStateProps =
  | { kind: 'loading' }
  | { kind: 'error'; onRetry: () => void; message?: string }
  | { kind: 'empty'; message: string };

/** Loading, failed and not-found states of the order pages, announced to screen readers. */
export function PageState(props: PageStateProps) {
  if (props.kind === 'loading') {
    return (
      <div role="status" className="flex min-h-48 items-center justify-center font-qb text-qb-ink-subtle">
        <Loader2 className="size-6 animate-spin motion-reduce:animate-none" aria-hidden="true" />
        <span className="sr-only">{t('orders.common.loading')}</span>
      </div>
    );
  }

  return (
    <div role={props.kind === 'error' ? 'alert' : 'status'} className="flex min-h-48 flex-col items-center justify-center gap-4 px-4 text-center font-qb">
      <p className="max-w-md text-qb-body text-qb-ink-secondary">
        {props.kind === 'error' ? (props.message ?? t('orders.common.load_error')) : props.message}
      </p>
      {props.kind === 'error' ? (
        <Button variant="outline" size="sm" onClick={props.onRetry}>
          {t('orders.common.retry')}
        </Button>
      ) : null}
    </div>
  );
}
