'use client';

/**
 * "Start a conversation with the seller" CTA used on the ad detail page.
 *
 * Three states:
 *  1. Signed-out user → routes to `/login?from=...` so they come back after
 *     auth and can retry.
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
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import type { Ad } from '@/lib/api/types';

interface Props {
  ad: Pick<Ad, 'id' | 'user_id'>;
}

export function StartConversationButton({ ad }: Props) {
  const router = useRouter();
  const { user, isAuthenticated, isHydrated } = useAuth();
  const startMutation = useStartConversationMutation();
  const [signedOutClicked, setSignedOutClicked] = useState(false);

  // Owner badge — they can't message themselves.
  if (isHydrated && isAuthenticated && user?.id === ad.user_id) {
    return (
      <span className="bg-coral/10 text-coral inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-bold">
        {t('messaging.own_ad_badge', 'هذا إعلانك')}
      </span>
    );
  }

  const handleClick = async () => {
    if (!isAuthenticated) {
      setSignedOutClicked(true);
      const from = encodeURIComponent(`/ads/${ad.id}`);
      router.push(`/login?from=${from}`);
      return;
    }
    try {
      const conversation = await startMutation.mutateAsync(ad.id);
      router.push(`/account/messages?c=${conversation.id}`);
    } catch (err) {
      const fallback = t('messaging.errors.send_failed', 'تعذّر بدء المحادثة');
      const message =
        err && typeof err === 'object' && 'messageKey' in err
          ? translateMaybeKey((err as { messageKey?: string }).messageKey) || fallback
          : fallback;
      toast.error(message);
    }
  };

  const pending = startMutation.isPending || signedOutClicked;

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
