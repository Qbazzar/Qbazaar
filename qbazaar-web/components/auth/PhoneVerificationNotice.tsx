'use client';

import { useRouter } from 'next/navigation';
import { ShieldCheckIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
        'bg-card flex gap-3',
        compact
          ? 'border-ink-200 items-center border-t p-3'
          : 'ring-foreground/10 flex-col items-start rounded-2xl p-5 ring-1 sm:flex-row sm:items-center',
        className,
      )}
    >
      <span className="bg-coral/10 text-coral inline-flex size-10 shrink-0 items-center justify-center rounded-xl">
        <ShieldCheckIcon className="size-5" aria-hidden />
      </span>
      <div className="min-w-0 flex-1 space-y-1">
        {compact ? null : (
          <p className="text-ink-900 text-sm font-semibold">
            {t('auth.phone_gate.title')}
          </p>
        )}
        <p className="text-muted-foreground text-xs leading-relaxed sm:text-sm">
          {t(`auth.phone_gate.body.${context}`)}
        </p>
      </div>
      <Button
        type="button"
        size="sm"
        onClick={goToVerification}
        className="shrink-0 rounded-full px-4 text-xs font-semibold"
      >
        {t('auth.phone_gate.cta')}
      </Button>
    </div>
  );
}
