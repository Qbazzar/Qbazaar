'use client';

/**
 * The "Add to Favorite" pill of a category page (category.html, 81:1629).
 * Its state is `is_favorited` of the cached category page, flipped at once
 * and put back if the request fails. Guests are sent to the login page and
 * return to this URL afterwards.
 */
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { apiErrorMessage } from '@/components/account/api-error-message';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { HeartIcon } from '@/components/design-system/icons';
import { TRIGGER } from '@/components/search/SaveSearchButton';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';
import { useCategoryPageQuery } from '@/lib/queries/categories';
import { useSetFavoriteCategoryMutation } from '@/lib/queries/favorite-categories';
import { cn } from '@/lib/utils';

interface FavoriteCategoryButtonProps {
  slug: string;
  variant?: keyof typeof TRIGGER;
  className?: string;
}

export function FavoriteCategoryButton({ slug, variant = 'header', className }: FavoriteCategoryButtonProps) {
  const router = useRouter();
  const { isAuthenticated, isHydrated } = useAuth();
  // A guest is never favourited, so the page is only fetched (it is cached, and shared with the overview) for a signed-in visitor.
  const page = useCategoryPageQuery(isHydrated && isAuthenticated ? slug : null);
  const mutation = useSetFavoriteCategoryMutation();
  const favorited = page.data?.is_favorited ?? false;
  const trigger = TRIGGER[variant];
  // A failed load must not leave the pill dead: pressing it tries again.
  const loadFailed = isAuthenticated && page.isError && !page.data;
  const signedInAndLoading = isAuthenticated && page.isPending && page.fetchStatus !== 'idle';

  const toggle = () => {
    if (!isAuthenticated) {
      router.push(`/login?from=${encodeURIComponent(currentLocationPath())}`);
      return;
    }
    if (loadFailed) {
      void page.refetch();
      return;
    }
    mutation.mutate({ slug, favorited: !favorited }, { onError: (error) => toast.error(apiErrorMessage(error)) });
  };

  return (
    <button
      type="button"
      onClick={toggle}
      disabled={!isHydrated || signedInAndLoading || mutation.isPending}
      // The name starts with the visible text (WCAG 2.5.3) and adds what pressing it does.
      aria-label={favorited ? t('catalog.favorite_category.remove', 'في المفضلة، إزالة القسم من المفضلة') : undefined}
      className={cn('shrink-0 font-qb disabled:pointer-events-none disabled:opacity-50', trigger.button, focusRing, className)}
    >
      <span className={cn('inline-flex items-center', trigger.face)}>
        {/* Orange on the pill; the phone circle draws it dark like its search neighbour. */}
        <Icon
          icon={HeartIcon}
          size={trigger.iconSize}
          className={cn(trigger.iconInk || 'text-qb-brand', favorited && 'fill-qb-brand text-qb-brand')}
        />
        <span className={trigger.label}>
          {favorited ? t('catalog.favorite_category.added', 'في المفضلة') : t('catalog.favorite_category.add', 'أضف إلى المفضلة')}
        </span>
      </span>
    </button>
  );
}
