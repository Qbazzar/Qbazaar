'use client';

/**
 * Home page client island — top 8 categories rendered as QBFront `.cat-card`s.
 *
 * Uses the same `useMainCategoriesQuery` hook as before but builds the markup
 * directly so it matches the prototype's category grid pixel-for-pixel.
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

export function HomeCategoryStrip() {
  const locale = getLocale();
  const { data, isLoading, isError } = useMainCategoriesQuery();

  if (isLoading) {
    return (
      <div className="cat-grid" aria-busy="true">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="cat-card animate-pulse"
            style={{ height: 72 }}
            aria-hidden="true"
          />
        ))}
      </div>
    );
  }
  if (isError || !data) {
    return (
      <p className="text-muted py-8 text-center text-sm">
        {t('categories.errors.not_found', 'تعذّر تحميل الأقسام')}
      </p>
    );
  }

  return (
    <div className="cat-grid">
      {data.slice(0, 8).map((cat) => (
        <Link key={cat.id} href={`/c/${cat.slug}`} className="cat-card">
          <span className="cat-card__icon">
            <DynamicIcon name={cat.icon} className="size-5" />
          </span>
          <div>
            <div className="cat-card__title">{localized(cat.name, locale)}</div>
            <div className="cat-card__count">
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
