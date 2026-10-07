'use client';

import { useRecentlyViewedQuery } from '@/lib/queries/recently-viewed';
import { useAuthStore } from '@/store/auth';
import { t } from '@/lib/i18n/messages';

import { HomeAdSection } from './HomeAdSection';

const PER_PAGE = 10;

/** The signed-in user's last viewed ads; hidden for guests and when empty. */
export function HomeRecentlyViewed() {
  // Recently viewed is auth-only, so guests never send the request.
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const { data, isLoading } = useRecentlyViewedQuery({ per_page: PER_PAGE }, { enabled: isAuthenticated });

  if (!isAuthenticated) return null;
  return (
    <HomeAdSection
      id="home-recently-viewed"
      title={t('recently_viewed.title', 'آخر ما شاهدت')}
      subtitle={t('recently_viewed.strip_heading', 'تابع من حيث وقفت')}
      action={{ href: '/account/recently-viewed', label: t('home.sections.view_all', 'عرض الكل') }}
      ads={data?.data}
      isLoading={isLoading}
    />
  );
}
