'use client';

/**
 * FavoriteButton — heart toggle used on AdCard (overlaid on the image) and on
 * the ad-detail sidebar (inline next to Share).
 *
 * - Live state comes from `useFavoritesStore` so every instance of this button
 *   on the page stays in sync after a single optimistic toggle.
 * - Unauthenticated clicks redirect to `/login?next={current}` instead of
 *   firing the mutation — the heart only fills for signed-in users.
 * - The mutation is optimistic at the store level; the success path simply
 *   reconciles with the server, and the error path rolls back.
 */
import { useMemo } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { HeartIcon } from 'lucide-react';

import { useFavoritesStore } from '@/store/favorites';
import { useToggleFavoriteMutation } from '@/lib/queries/favorites';
import { useAuthStore } from '@/store/auth';
import { ApiClientError } from '@/lib/api/auth';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface Props {
  adId: string;
  /** Optional initial state used as a fallback before the store hydrates. */
  initialFavorited?: boolean;
  size?: 'sm' | 'md';
  /** Extra classes for the outer button — used to position the overlay. */
  className?: string;
  /** Render a label next to the heart (used in the ad-detail sidebar). */
  withLabel?: boolean;
}

const SIZE_CLS: Record<NonNullable<Props['size']>, string> = {
  sm: 'size-8 [&_svg]:size-4',
  md: 'size-10 [&_svg]:size-5',
};

export function FavoriteButton({
  adId,
  initialFavorited = false,
  size = 'sm',
  className,
  withLabel = false,
}: Props) {
  const router = useRouter();
  const pathname = usePathname();

  const storeHasId = useFavoritesStore((s) => s.ids.has(adId));
  // Until the store has been hydrated (no entry yet), we fall back to the
  // server-provided `initialFavorited` so the heart doesn't flicker.
  const favorited = storeHasId || initialFavorited;

  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const isHydrated = useAuthStore((s) => s.isHydrated);
  const toggleMutation = useToggleFavoriteMutation();

  const labelKey = favorited
    ? 'favorites.tooltip.remove'
    : 'favorites.tooltip.add';
  const label = t(labelKey);

  const handleClick = useMemo(() => {
    return (event: React.MouseEvent<HTMLButtonElement>) => {
      // The button sits inside a Link on AdCard — swallow the click so the
      // parent <a> doesn't navigate to the ad detail page.
      event.preventDefault();
      event.stopPropagation();

      // Wait for auth hydration so we don't bounce a logged-in user to /login
      // just because the access token hasn't rehydrated yet.
      if (isHydrated && !isAuthenticated) {
        toast.info(t('favorites.sign_in_required'));
        const next = encodeURIComponent(pathname || '/');
        router.push(`/login?next=${next}`);
        return;
      }

      toggleMutation.mutate(adId, {
        onError: (err) => {
          const code = err instanceof ApiClientError ? err.code : '';
          if (code === 'FAVORITE_AD_NOT_FOUND') {
            toast.error(t('favorites.errors.ad_not_found'));
            return;
          }
          toast.error(
            translateMaybeKey(`favorites.errors.${code.toLowerCase()}`) ||
              t('common.error'),
          );
        },
      });
    };
  }, [adId, isAuthenticated, isHydrated, pathname, router, toggleMutation]);

  return (
    <button
      type="button"
      onClick={handleClick}
      aria-label={label}
      aria-pressed={favorited}
      title={label}
      disabled={toggleMutation.isPending}
      className={cn(
        'inline-flex items-center justify-center rounded-full bg-white/90 text-ink-700 shadow-sm ring-1 ring-black/5 backdrop-blur-sm transition-colors',
        'hover:bg-white hover:text-coral focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-coral focus-visible:ring-offset-2',
        'disabled:cursor-progress disabled:opacity-70',
        withLabel ? 'gap-2 px-3 py-2 text-sm' : SIZE_CLS[size],
        favorited && 'text-coral',
        className,
      )}
    >
      <HeartIcon
        aria-hidden="true"
        className={cn(
          'transition-colors',
          favorited ? 'fill-coral text-coral' : 'fill-transparent',
        )}
      />
      {withLabel ? <span className="font-medium">{label}</span> : null}
    </button>
  );
}
