import type { ReactNode } from 'react';
import { CircleAlert } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { t } from '@/lib/i18n/messages';

interface RetryPanelProps {
  onRetry: () => void;
  /** A retry is in flight: the button ignores presses but keeps keyboard focus. */
  retrying?: boolean;
  /** Shown next to the retry button. */
  secondaryAction?: ReactNode;
}

/** Failed load with a retry, in the panel of the "Search Not Found" screens (655:55973). */
export function RetryPanel({ onRetry, retrying = false, secondaryAction }: RetryPanelProps) {
  return (
    <StatePanel
      icon={<StateIcon icon={CircleAlert} tone="muted" />}
      title={t('errors.generic_heading')}
      description={t('errors.generic_body')}
      action={
        <div className="flex flex-wrap justify-center gap-3">
          {/* aria-disabled rather than disabled: a disabled button drops the keyboard focus it holds. */}
          <Button size="sm" aria-disabled={retrying || undefined} onClick={retrying ? undefined : onRetry}>
            {t('errors.retry')}
          </Button>
          {secondaryAction}
        </div>
      }
    />
  );
}
