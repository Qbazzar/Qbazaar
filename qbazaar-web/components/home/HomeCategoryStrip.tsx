'use client';

/**
 * Home page client island — top 8 categories rendered through CategoryGrid.
 */
import { CategoryGrid } from '@/components/categories/CategoryGrid';
import { useMainCategoriesQuery } from '@/lib/queries/categories';
import { t } from '@/lib/i18n/messages';

export function HomeCategoryStrip() {
  const { data, isLoading, isError } = useMainCategoriesQuery();

  if (isLoading) {
    return (
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="bg-cream-200 h-20 animate-pulse rounded-xl"
            aria-hidden="true"
          />
        ))}
      </div>
    );
  }
  if (isError || !data) {
    return (
      <p className="text-ink-500 py-8 text-center text-sm">
        {t('categories.errors.not_found', 'تعذّر تحميل الأقسام')}
      </p>
    );
  }
  return <CategoryGrid categories={data.slice(0, 8)} />;
}
