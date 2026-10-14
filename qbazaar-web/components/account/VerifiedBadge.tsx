
import { Badge } from '@/components/design-system/Badge';
import { CheckCircleIcon } from '@/components/design-system/icons';
import { t } from '@/lib/i18n/messages';

/** Green "Verified" status chip, in the label face of the design's status chips. */
export function VerifiedBadge({ className }: { className?: string }) {
  return (
    <Badge tone="success" size="sm" font="label" className={className}>
      <CheckCircleIcon aria-hidden="true" />
      {t('account.verification.status.verified')}
    </Badge>
  );
}
