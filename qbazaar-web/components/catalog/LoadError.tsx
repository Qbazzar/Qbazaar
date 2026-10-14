
import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { AlertCircleIcon } from '@/components/design-system/icons';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface LoadErrorProps {
  title: string;
  onRetry: () => void;
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

/** A failed request of a catalog page, announced, with a retry button. The design has no frame for it. */
export function LoadError({ title, onRetry, headingLevel, className }: LoadErrorProps) {
  return (
    <div role="alert" className={cn('rounded-qb-xl border border-qb-line bg-qb-surface', className)}>
      <EmptyState
        icon={<Icon icon={AlertCircleIcon} size="lg" />}
        title={title}
        headingLevel={headingLevel}
        action={
          <Button variant="secondary" size="sm" onClick={onRetry}>
            {t('common.retry', 'إعادة المحاولة')}
          </Button>
        }
      />
    </div>
  );
}
