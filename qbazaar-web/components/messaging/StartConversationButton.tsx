'use client';

/**
 * Opens (or starts) the conversation with the seller about an ad and goes to
 * the inbox with `?c={id}` set. Guests go to login first and signed-in users
 * without a verified phone to phone verification; both come back here.
 *
 * The ad detail uses it for "Send Message". Whether the button shows at all
 * (own ad, sold ad) is the caller's call.
 */
import { useState, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { Loader2Icon, MessageSquareText, type LucideIcon } from 'lucide-react';
import { toast } from 'sonner';

import { Button, type ButtonProps } from '@/components/design-system/Button';
import { useStartConversationMutation } from '@/lib/queries/messaging';
import { usePhoneVerificationGate } from '@/hooks/usePhoneVerificationGate';
import { isPhoneNotVerifiedError } from '@/lib/auth/phone-gate';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import type { Ad } from '@/lib/api/types';

interface StartConversationButtonProps extends Omit<ButtonProps, 'onClick' | 'children'> {
  ad: Pick<Ad, 'id'>;
  icon?: LucideIcon;
  children: ReactNode;
}

export function StartConversationButton({
  ad,
  icon: Glyph = MessageSquareText,
  children,
  disabled,
  ...buttonProps
}: StartConversationButtonProps) {
  const router = useRouter();
  const { status: gateStatus, ensureVerifiedPhone } = usePhoneVerificationGate();
  const startMutation = useStartConversationMutation();
  const [redirecting, setRedirecting] = useState(false);

  const handleClick = async () => {
    if (!ensureVerifiedPhone(`/ads/${ad.id}`)) {
      setRedirecting(true);
      return;
    }
    try {
      const conversation = await startMutation.mutateAsync(ad.id);
      router.push(`/account/messages?c=${conversation.id}`);
    } catch (err) {
      if (isPhoneNotVerifiedError(err)) return;
      const fallback = t('messaging.errors.send_failed', 'تعذّر بدء المحادثة');
      const message =
        err && typeof err === 'object' && 'messageKey' in err
          ? translateMaybeKey((err as { messageKey?: string }).messageKey) || fallback
          : fallback;
      toast.error(message);
    }
  };

  const pending = startMutation.isPending || redirecting || gateStatus === 'loading';

  return (
    <Button {...buttonProps} onClick={handleClick} disabled={disabled || pending} aria-busy={pending || undefined}>
      {pending ? <Loader2Icon className="animate-spin" aria-hidden /> : <Glyph aria-hidden />}
      {children}
    </Button>
  );
}
