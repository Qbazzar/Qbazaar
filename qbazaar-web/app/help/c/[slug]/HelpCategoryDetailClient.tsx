'use client';

/**
 * Help category detail — breadcrumb, header (icon + name + description) and
 * the list of articles in the category. The 404 case falls through to the
 * helpful empty-state that links back to the help index.
 */
import Link from 'next/link';
import { ChevronLeftIcon, ChevronRightIcon, Loader2Icon } from 'lucide-react';

import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { HelpArticleCard } from '@/components/help/HelpArticleCard';
import { useHelpCategoryQuery } from '@/lib/queries/help';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';

interface Props {
  slug: string;
}

export function HelpCategoryDetailClient({ slug }: Props) {
  const locale = getLocale();
  const Chevron = locale === 'ar' ? ChevronLeftIcon : ChevronRightIcon;
  const { data, isLoading, isError, error } = useHelpCategoryQuery(slug);

  if (isLoading) {
    return (
      <div className="flex min-h-[50svh] items-center justify-center" role="status">
        <Loader2Icon
          className="text-muted-foreground size-6 animate-spin"
          aria-hidden
        />
      </div>
    );
  }

  if (isError || !data) {
    const notFound =
      error instanceof ApiClientError && error.code === 'HELP_CATEGORY_NOT_FOUND';
    return (
      <div className="container" style={{ paddingTop: 64, paddingBottom: 64 }}>
        <div className="card card--lg text-center">
          <h1 className="text-h2 text-ink-900">
            {notFound
              ? t('help.category_not_found', 'لم نعثر على هذه الفئة')
              : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
          </h1>
          <p className="text-ink-700 mt-2 text-sm">
            <Link className="text-coral underline" href="/help">
              {t('help.back_to_help', 'العودة إلى مركز المساعدة')}
            </Link>
          </p>
        </div>
      </div>
    );
  }

  const name = localized(data.name, locale);
  const description = localized(data.description, locale);
  const articles = data.articles ?? [];

  return (
    <main>
      <div className="container" style={{ maxWidth: 920, paddingBottom: 64 }}>
        <nav aria-label="breadcrumb" className="cms-breadcrumb">
          <Link href="/help">{t('help.title', 'مركز المساعدة')}</Link>
          <Chevron className="cms-breadcrumb__sep size-3.5" aria-hidden />
          <span className="text-ink-900">{name}</span>
        </nav>

        <header className="mt-6 flex items-start gap-4">
          <span className="help-icon-wrap" aria-hidden>
            <DynamicIcon name={data.icon ?? 'BookOpen'} className="size-6" />
          </span>
          <div className="min-w-0">
            <h1 className="text-h2 text-ink-900">{name}</h1>
            {description ? (
              <p className="text-ink-700 mt-2 text-sm leading-relaxed">
                {description}
              </p>
            ) : null}
          </div>
        </header>

        <section className="mt-8">
          <h2 className="text-h3 text-ink-900 mb-4">
            {t('help.articles_in_category', 'المقالات في هذه الفئة')}
          </h2>
          {articles.length === 0 ? (
            <p className="text-ink-500 text-sm">
              {t('help.no_articles', 'لا توجد مقالات في هذه الفئة بعد')}
            </p>
          ) : (
            <ul className="flex flex-col gap-3">
              {articles.map((article) => (
                <li key={article.id} className="list-none">
                  <HelpArticleCard article={article} />
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </main>
  );
}
