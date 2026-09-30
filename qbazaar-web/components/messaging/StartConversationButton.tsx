'use client';

/**
 * "Start a conversation with the seller" CTA used on the ad detail page.
 *
 * Three states:
 *  1. Signed-out user → routes to `/login?from=...`; a signed-in user without
 *     a verified phone → routes to phone verification. Both come back here.
 *  2. Ad owner → renders a non-actionable badge instead.
 *  3. Anyone else → calls `useStartConversationMutation` and routes to the
 *     inbox with `?c={id}` set.
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { MessageSquareIcon, Loader2Icon } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useStartConversationMutation } from '@/lib/queries/messaging';
import { useAuth } from '@/hooks/useAuth';
import { usePhoneVerificationGate } from '@/hooks/usePhoneVerificationGate';
import { isPhoneNotVerifiedError } from '@/lib/auth/phone-gate';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import type { Ad } from '@/lib/api/types';

interface Props {
  ad: Pick<Ad, 'id' | 'user_id' | 'status'>;
}

export function StartConversationButton({ ad }: Props) {
  const router = useRouter();
  const { user, isAuthenticated, isHydrated } = useAuth();
  const { status: gateStatus, ensureVerifiedPhone } = usePhoneVerificationGate();
  const startMutation = useStartConversationMutation();
  const [redirecting, setRedirecting] = useState(false);

  // Sold ad — no contact action; surface the sold state instead (shown to
  // everyone, including the owner).
  if (ad.status === 'sold') {
    return (
      <span className="bg-ink-100 text-ink-600 inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-bold">
        {t('ads.status.sold', 'تم البيع')}
      </span>
    );
  }

  // Owner badge — they can't message themselves.
  if (isHydrated && isAuthenticated && user?.id === ad.user_id) {
    return (
      <span className="bg-coral/10 text-coral inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-bold">
        {t('messaging.own_ad_badge', 'هذا إعلانك')}
      </span>
    );
  }

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

  const pending =
    startMutation.isPending || redirecting || gateStatus === 'loading';

  return (
    <Button
      type="button"
      size="lg"
      onClick={handleClick}
      disabled={pending}
      className="bg-coral hover:bg-coral/90 rounded-full text-white"
    >
      {pending ? (
        <Loader2Icon className="size-4 animate-spin" aria-hidden />
      ) : (
        <MessageSquareIcon className="size-4" aria-hidden />
      )}
      {t('messaging.start_chat', 'تواصل مع البائع')}
    </Button>
  );
}
