'use client';

/**
 * Categories index — QBFront port. Renders all main categories as a
 * `.category-grid` of `.category-card`s.
 */
import Link from 'next/link';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { useMainCategoriesQuery } from '@/lib/queries/categories';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

function formatCount(count: number, locale: 'ar' | 'en'): string {
  const lang = locale === 'ar' ? 'ar-EG' : 'en-US';
  return new Intl.NumberFormat(lang).format(count);
}

export function CategoriesIndexClient() {
  const locale = getLocale();
  const { data, isLoading, isError, refetch } = useMainCategoriesQuery();

  if (isLoading) {
    return (
      <div className="category-grid" aria-busy="true">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="category-card animate-pulse"
            style={{ height: 80 }}
            aria-hidden
          />
        ))}
      </div>
    );
  }

  if (isError || !data) {
    return (
      <div className="empty-state">
        <div className="empty-state__title">
          {t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </div>
        <button
          type="button"
          onClick={() => refetch()}
          className="btn btn--primary btn--pill"
          style={{ marginTop: 16 }}
        >
          {t('common.retry', 'إعادة المحاولة')}
        </button>
      </div>
    );
  }

  return (
    <div className="category-grid">
      {data.map((cat) => (
        <Link key={cat.id} href={`/c/${cat.slug}`} className="category-card">
          <span className="category-card__icon">
            <DynamicIcon name={cat.icon} className="size-5" />
          </span>
          <div>
            <div className="category-card__name">{localized(cat.name, locale)}</div>
            <div className="category-card__count">
              {t(
                'categories.ads_count',
                { count: formatCount(cat.ads_count, locale) },
                `${formatCount(cat.ads_count, locale)} إعلان`,
              )}
            </div>
          </div>
        </Link>
      ))}
    </div>
  );
}
