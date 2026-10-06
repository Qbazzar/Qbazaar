'use client';

/**
 * Follow / unfollow a seller. Guests are sent to login and come back; the
 * button is not shown on your own profile. While the viewer's own copy of the
 * profile is still loading, the state isn't known yet, so the button waits.
 */
import { Check, Loader2Icon } from 'lucide-react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { Button, type ButtonProps } from '@/components/design-system/Button';
import { useAuth } from '@/hooks/useAuth';
import { ApiClientError } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';
import { useFollowMutation } from '@/lib/queries/users';
import { cn } from '@/lib/utils';

interface FollowButtonProps extends Pick<ButtonProps, 'size' | 'className'> {
  userId: string;
  /** Completes the accessible name, e.g. "Follow BonTon". */
  name: string;
  isFollowing: boolean;
  /** False until the profile has been fetched with the viewer's session. */
  stateKnown: boolean;
  /** "Follow Seller" on the private seller card, "Follow" elsewhere. */
  label?: string;
}

const ERROR_KEYS: Record<string, string> = {
  FOLLOW_001: 'users.follow.errors.self',
  FOLLOW_002: 'users.follow.errors.blocked',
  USER_001: 'users.profile.not_found_title',
  RATE_LIMIT_EXCEEDED: 'auth.errors.RATE_LIMIT_EXCEEDED',
};

export function FollowButton({ userId, name, isFollowing, stateKnown, label, size, className }: FollowButtonProps) {
  const router = useRouter();
  const { user, isAuthenticated, isHydrated } = useAuth();
  const mutation = useFollowMutation(userId);

  if (isHydrated && user?.id === userId) return null;

  const busy = mutation.isPending || !isHydrated || (isAuthenticated && !stateKnown);

  const onClick = () => {
    if (!isAuthenticated) {
      router.push(`/login?from=${encodeURIComponent(currentLocationPath())}`);
      return;
    }
    mutation.mutate(!isFollowing, {
      onError: (err) => {
        const key = err instanceof ApiClientError ? ERROR_KEYS[err.code] : undefined;
        toast.error(t(key ?? 'common.error'));
      },
    });
  };

  return (
    <Button
      variant={isFollowing ? 'secondary' : 'primary'}
      size={size}
      onClick={onClick}
      disabled={busy}
      aria-busy={mutation.isPending || undefined}
      className={cn('font-medium', className)}
    >
      {mutation.isPending ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
      {!mutation.isPending && isFollowing ? <Check aria-hidden /> : null}
      <span>
        {isFollowing ? t('users.follow.following') : (label ?? t('users.follow.follow'))}
        <span className="sr-only"> {name}</span>
      </span>
    </Button>
  );
}
