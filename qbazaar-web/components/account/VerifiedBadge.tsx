import { CircleCheck } from 'lucide-react';

import { Badge } from '@/components/design-system/Badge';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** Green "Verified" status chip, in the label face of the design's status chips. */
export function VerifiedBadge({ className }: { className?: string }) {
  return (
    <Badge tone="success" size="sm" className={cn('font-qb-label', className)}>
      <CircleCheck aria-hidden="true" />
      {t('account.verification.status.verified')}
    </Badge>
  );
}
