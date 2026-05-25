'use client';

/**
 * Help center search results — driven by the `?q=` query string via nuqs so
 * the page stays shareable. We re-render the same `HelpSearchBar` with the
 * suggestions panel suppressed (the body of the page already does the
 * full-list rendering).
 */
import { parseAsString, useQueryState } from 'nuqs';
import { Loader2Icon } from 'lucide-react';
import Link from 'next/link';

import { HelpSearchBar } from '@/components/help/HelpSearchBar';
import { HelpArticleCard } from '@/components/help/HelpArticleCard';
import { useHelpSearchQuery } from '@/lib/queries/help';
import { t } from '@/lib/i18n/messages';

export function HelpSearchClient() {
  const [q] = useQueryState('q', parseAsString.withDefault(''));
  const trimmed = q.trim();
  const enabled = trimmed.length >= 2;
  const { data: results, isFetching, isError } = useHelpSearchQuery(trimmed);

  return (
    <main>
      <div className="container" style={{ paddingTop: 32, paddingBottom: 64 }}>
        <div className="help-hero">
          <h1 className="help-hero__h">{t('help.title', 'مركز المساعدة')}</h1>
          <p className="help-hero__sub">
            {t('help.subtitle', 'ابحث في مركز المساعدة أو اختر موضوعاً.')}
          </p>
          <HelpSearchBar initialQuery={q} hideSuggestions />
        </div>

        <section className="mt-10">
          {!enabled ? (
            <p className="text-ink-500 text-center text-sm">
              {t(
                'help.search_min_chars',
                'اكتب حرفين على الأقل لبدء البحث',
              )}
            </p>
          ) : isFetching && !results ? (
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
          ) : !results || results.length === 0 ? (
            <div className="card card--lg text-center">
              <p className="text-ink-700 text-sm">
                {t('help.no_results', 'لا توجد نتائج')}
              </p>
              <Link className="text-coral mt-3 inline-block text-sm underline" href="/help">
                {t('help.back_to_help', 'العودة إلى مركز المساعدة')}
              </Link>
            </div>
          ) : (
            <>
              <h2 className="text-h3 text-ink-900 mb-4">
                {t(
                  'help.search_results',
                  { count: String(results.length) },
                  `Found ${results.length} results`,
                )}
              </h2>
              <ul className="flex flex-col gap-3">
                {results.map((article) => (
                  <li key={article.id} className="list-none">
                    <HelpArticleCard article={article} />
                  </li>
                ))}
              </ul>
            </>
          )}
        </section>
      </div>
    </main>
  );
}
