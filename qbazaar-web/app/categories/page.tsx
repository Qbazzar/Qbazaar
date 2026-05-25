import type { Metadata } from 'next';
import { t } from '@/lib/i18n/messages';
import { CategoriesIndexClient } from './CategoriesIndexClient';

/**
 * Top-level category index — `/categories`. QBFront port using the
 * `.category-grid` + `.category-card` markup from QBFront/ar/shop pages.
 */
export const metadata: Metadata = {
  title: t('categories.all', 'الأقسام'),
};

export default function CategoriesIndexPage() {
  return (
    <main>
      <div className="container" style={{ paddingTop: 32, paddingBottom: 48 }}>
        <header style={{ marginBottom: 28 }}>
          <h1 className="cat-page__title">{t('categories.all', 'الأقسام')}</h1>
          <p className="cat-page__meta" style={{ marginTop: 8 }}>
            {t(
              'categories.index_subtitle',
              'اكتشف ما يبيعه ويشتريه جيرانك في قطر',
            )}
          </p>
        </header>
        <CategoriesIndexClient />
      </div>
    </main>
  );
}
