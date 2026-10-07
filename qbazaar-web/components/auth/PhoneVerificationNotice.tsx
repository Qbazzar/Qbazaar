'use client';

import { useRouter } from 'next/navigation';
import { ShieldCheck } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import { phoneVerificationHref } from '@/lib/auth/phone-gate';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';

type NoticeContext = 'post_ad' | 'messaging';

interface Props {
  context: NoticeContext;
  /** Slim single-row layout for tight spots such as the chat composer. */
  compact?: boolean;
  className?: string;
}

/** Shown in place of a phone-gated form so users verify before filling it. */
export function PhoneVerificationNotice({ context, compact = false, className }: Props) {
  const router = useRouter();

  const goToVerification = () => {
    router.push(phoneVerificationHref(currentLocationPath()));
  };

  return (
    <div
      role="status"
      className={cn(
        'flex gap-3 bg-qb-surface font-qb',
        compact
          ? 'items-center border-t border-qb-line p-3'
          : 'flex-col items-start rounded-qb-xl border border-qb-line p-5 shadow-qb-card qb-tablet:flex-row qb-tablet:items-center',
        className,
      )}
    >
      <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-qb-md bg-qb-brand-soft text-qb-brand">
        <ShieldCheck className="size-5" aria-hidden="true" />
      </span>
      <div className="min-w-0 flex-1">
        {compact ? null : (
          <p className="text-qb-body font-semibold text-qb-ink">{t('auth.phone_gate.title')}</p>
        )}
        <p className={cn('text-qb-label text-qb-ink-secondary qb-tablet:text-qb-caption', compact ? null : 'mt-1')}>
          {t(`auth.phone_gate.body.${context}`)}
        </p>
      </div>
      <Button size="sm" onClick={goToVerification}>
        {t('auth.phone_gate.cta')}
      </Button>
    </div>
  );
}
