'use client';

/**
 * Follow / unfollow a seller. Guests are sent to login and come back; the
 * button is not shown on your own profile. While the viewer's own copy of the
 * profile is still loading, the state isn't known yet, so the button waits.
 * During the request it keeps its focus, and the result is announced.
 *
 * Looks: the orange button of the seller pages (it darkens under the
 * pointer, as polish.css does for every orange button); once followed it
 * turns peach with a brand outline and a star, "Following" (283:6955).
 */
import { Loader2Icon, Star, type LucideIcon } from 'lucide-react';
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
  /** "+ Follow" on the private seller card, "Follow" elsewhere. */
  label?: string;
  /** Icon before "Follow" (the company card's star); the followed state always shows the star. */
  icon?: LucideIcon;
}

const ERROR_KEYS: Record<string, string> = {
  FOLLOW_001: 'users.follow.errors.self',
  FOLLOW_002: 'users.follow.errors.blocked',
  USER_001: 'users.profile.not_found_title',
  RATE_LIMIT_EXCEEDED: 'auth.errors.RATE_LIMIT_EXCEEDED',
};

export function FollowButton({ userId, name, isFollowing, stateKnown, label, icon, size, className }: FollowButtonProps) {
  const router = useRouter();
  const { user, isAuthenticated, isHydrated } = useAuth();
  const mutation = useFollowMutation(userId);

  if (isHydrated && user?.id === userId) return null;

  const waiting = !isHydrated || (isAuthenticated && !stateKnown);

  const onClick = () => {
    if (mutation.isPending) return;
    if (!isAuthenticated) {
      router.push(`/login?from=${encodeURIComponent(currentLocationPath())}`);
      return;
    }
    mutation.mutate(!isFollowing, {
      onSuccess: (state) => {
        toast.success(t(state.following ? 'users.follow.followed' : 'users.follow.unfollowed', { name }));
      },
      onError: (err) => {
        const key = err instanceof ApiClientError ? ERROR_KEYS[err.code] : undefined;
        toast.error(t(key ?? 'common.error'));
      },
    });
  };

  const Glyph = isFollowing ? Star : icon;

  return (
    <Button
      variant={isFollowing ? 'secondary' : 'primary'}
      size={size}
      onClick={onClick}
      disabled={waiting}
      aria-disabled={mutation.isPending || undefined}
      aria-busy={mutation.isPending || undefined}
      className={cn('font-medium', isFollowing && 'bg-qb-brand-soft hover:bg-qb-surface hover:text-qb-brand', className)}
    >
      {mutation.isPending ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
      {!mutation.isPending && Glyph ? <Glyph aria-hidden strokeWidth={1.7} /> : null}
      <span>
        {isFollowing ? t('users.follow.following') : (label ?? t('users.follow.follow'))}
        <span className="sr-only"> {name}</span>
      </span>
    </Button>
  );
}
