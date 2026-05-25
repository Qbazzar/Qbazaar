'use client';

/**
 * Help article detail.
 *
 * Layout: breadcrumb -> title -> body -> "was this helpful?" Yes/No buttons
 * (no-op for now, console.info so devs can iterate) -> related articles strip.
 * Related articles come from the same category, excluding the current slug.
 */
import { useState } from 'react';
import Link from 'next/link';
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  EyeIcon,
  Loader2Icon,
  ThumbsDownIcon,
  ThumbsUpIcon,
} from 'lucide-react';
import { toast } from 'sonner';

import { MarkdownContent } from '@/components/cms/MarkdownContent';
import { HelpArticleCard } from '@/components/help/HelpArticleCard';
import {
  useHelpArticleQuery,
  useHelpCategoryQuery,
} from '@/lib/queries/help';
import { ApiClientError } from '@/lib/api/auth';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface Props {
  slug: string;
}

export function HelpArticleClient({ slug }: Props) {
  const locale = getLocale();
  const Chevron = locale === 'ar' ? ChevronLeftIcon : ChevronRightIcon;
  const { data: article, isLoading, isError, error } = useHelpArticleQuery(slug);
  const categorySlug = article?.category.slug ?? '';
  const { data: category } = useHelpCategoryQuery(categorySlug);
  const [feedback, setFeedback] = useState<'yes' | 'no' | null>(null);

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

  if (isError || !article) {
    const notFound =
      error instanceof ApiClientError && error.code === 'HELP_ARTICLE_NOT_FOUND';
    return (
      <div className="container" style={{ paddingTop: 64, paddingBottom: 64 }}>
        <div className="card card--lg text-center">
          <h1 className="text-h2 text-ink-900">
            {notFound
              ? t('help.article_not_found', 'لم نعثر على هذا المقال')
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

  const title = localized(article.title, locale);
  const body = localized(article.body, locale);
  const categoryName = localized(article.category.name, locale);
  const related = (category?.articles ?? []).filter(
    (a) => a.slug !== article.slug,
  );

  const sendFeedback = (value: 'yes' | 'no') => {
    setFeedback(value);
    // Placeholder — analytics endpoint lands in a later sprint.
    console.info('[help.feedback]', { slug: article.slug, value });
    toast.success(
      value === 'yes'
        ? t('help.feedback_thanks_yes', 'شكراً لك! يسعدنا أن المقال كان مفيداً')
        : t('help.feedback_thanks_no', 'شكراً لإخبارنا — سنحاول تحسين المقال'),
    );
  };

  return (
    <main>
      <article className="container" style={{ maxWidth: 820, paddingBottom: 64 }}>
        <nav aria-label="breadcrumb" className="cms-breadcrumb">
          <Link href="/help">{t('help.title', 'مركز المساعدة')}</Link>
          <Chevron className="cms-breadcrumb__sep size-3.5" aria-hidden />
          <Link href={`/help/c/${article.category.slug}`}>{categoryName}</Link>
          <Chevron className="cms-breadcrumb__sep size-3.5" aria-hidden />
          <span className="text-ink-900 truncate">{title}</span>
        </nav>

        <header className="mt-6 mb-6">
          <h1 className="text-h1 text-ink-900">{title}</h1>
          <p className="text-ink-500 mt-2 flex items-center gap-2 text-xs">
            <EyeIcon className="size-3.5" aria-hidden />
            {t(
              'help.views_count',
              { count: String(article.views_count) },
              `${article.views_count} views`,
            )}
          </p>
        </header>

        <MarkdownContent html={body} />

        <section className="bg-cream-100 ring-ink-200 mt-10 rounded-2xl p-6 ring-1">
          <h2 className="text-ink-900 text-base font-semibold">
            {t('help.helpful_question', 'هل كان هذا المقال مفيداً؟')}
          </h2>
          <div className="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => sendFeedback('yes')}
              disabled={feedback !== null}
              className={cn(
                'border-ink-200 hover:border-coral inline-flex items-center gap-2 rounded-full border bg-white px-4 py-2 text-sm font-medium transition-colors disabled:opacity-60',
                feedback === 'yes' && 'border-coral bg-coral/10 text-coral',
              )}
            >
              <ThumbsUpIcon className="size-4" aria-hidden />
              {t('help.helpful_yes', 'نعم')}
            </button>
            <button
              type="button"
              onClick={() => sendFeedback('no')}
              disabled={feedback !== null}
              className={cn(
                'border-ink-200 hover:border-coral inline-flex items-center gap-2 rounded-full border bg-white px-4 py-2 text-sm font-medium transition-colors disabled:opacity-60',
                feedback === 'no' && 'border-coral bg-coral/10 text-coral',
              )}
            >
              <ThumbsDownIcon className="size-4" aria-hidden />
              {t('help.helpful_no', 'لا')}
            </button>
          </div>
        </section>

        {related.length > 0 ? (
          <section className="mt-10">
            <h2 className="text-h3 text-ink-900 mb-4">
              {t('help.related_articles', 'مقالات ذات صلة')}
            </h2>
            <ul className="flex flex-col gap-3">
              {related.slice(0, 5).map((a) => (
                <li key={a.id} className="list-none">
                  <HelpArticleCard article={a} />
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </article>
    </main>
  );
}
