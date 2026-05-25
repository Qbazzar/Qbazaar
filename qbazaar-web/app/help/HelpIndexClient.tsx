'use client';

/**
 * Help center landing — port of `QBFront/help.html`.
 *
 * Sections: hero with search bar, then a responsive grid of category cards.
 * The FAQ section from the prototype is intentionally dropped — the CMS-
 * driven articles + categories cover that surface dynamically.
 */
import { Loader2Icon } from 'lucide-react';

import { HelpSearchBar } from '@/components/help/HelpSearchBar';
import { HelpCategoryCard } from '@/components/help/HelpCategoryCard';
import { useHelpCategoriesQuery } from '@/lib/queries/help';
import { t } from '@/lib/i18n/messages';

export function HelpIndexClient() {
  const { data: categories, isLoading, isError } = useHelpCategoriesQuery();

  return (
    <main>
      <div className="container" style={{ paddingTop: 32 }}>
        <div className="help-hero">
          <h1 className="help-hero__h">{t('help.title', 'كيف يمكننا مساعدتك؟')}</h1>
          <p className="help-hero__sub">
            {t(
              'help.subtitle',
              'ابحث في مركز المساعدة أو اختر موضوعاً. معظم الأسئلة لها إجابات في أقل من 30 ثانية.',
            )}
          </p>
          <HelpSearchBar />
        </div>

        <section style={{ paddingTop: 24, paddingBottom: 48 }}>
          <h2 className="text-h3 text-ink-900 mb-4">
            {t('help.categories_title', 'تصفّح حسب الموضوع')}
          </h2>

          {isLoading ? (
            <div className="flex justify-center py-12" role="status">
              <Loader2Icon
                className="text-muted-foreground size-6 animate-spin"
                aria-hidden
              />
            </div>
          ) : isError ? (
            <p className="text-destructive py-8 text-center text-sm">
              {t('common.error', 'حدث خطأ، حاول مرة أخرى')}
            </p>
          ) : !categories || categories.length === 0 ? (
            <p className="text-ink-500 py-8 text-center text-sm">
              {t('help.no_categories', 'لا توجد فئات مساعدة بعد')}
            </p>
          ) : (
            <div className="topic-grid">
              {categories.map((cat) => (
                <HelpCategoryCard key={cat.id} category={cat} />
              ))}
            </div>
          )}
        </section>
      </div>
    </main>
  );
}
