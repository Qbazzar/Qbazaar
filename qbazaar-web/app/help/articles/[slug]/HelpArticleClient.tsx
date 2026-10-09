'use client';

/**
 * Help article. No Figma frame: the same page frame and white body panel as
 * the CMS pages, then the feedback question and the topic's other articles.
 */
import { ContentPanel } from '@/components/cms/ContentPanel';
import { MarkdownContent } from '@/components/cms/MarkdownContent';
import { PageShell } from '@/components/design-system/PageShell';
import { BrowseTopicsLink } from '@/components/help/BrowseTopicsLink';
import { HelpArticleCard } from '@/components/help/HelpArticleCard';
import { HelpContactCard } from '@/components/help/HelpContactCard';
import { HelpFeedback } from '@/components/help/HelpFeedback';
import { SkeletonLine, SkeletonText } from '@/components/help/HelpSkeletons';
import { ErrorView } from '@/components/status/ErrorView';
import { NotFoundView } from '@/components/status/NotFoundView';
import { useRetry } from '@/hooks/useRetry';
import type { HelpErrorCode } from '@/lib/api/types';
import { useHelpArticleQuery, useHelpCategoryQuery } from '@/lib/queries/help';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';

const ARTICLE_NOT_FOUND: HelpErrorCode = 'HELP_001';
const RELATED_LIMIT = 5;

export function HelpArticleClient({ slug }: { slug: string }) {
  const { data: article, isError, error, refetch } = useHelpArticleQuery(slug);
  const { retrying, retry } = useRetry(refetch);
  const { data: category } = useHelpCategoryQuery(article?.category.slug ?? '');
  const helpCrumbs = [
    { label: t('home.breadcrumb'), href: '/' },
    { label: t('help.title'), href: '/help' },
  ];

  if (!article && (isError || retrying)) {
    const trail = [{ label: t('help.title'), href: '/help' }];
    if (error?.code === ARTICLE_NOT_FOUND) {
      // The page answered 200 before the browser learnt the article is gone, so keep it out of the index.
      return (
        <>
          <title>{`${t('errors.not_found_title')} | QBazaar`}</title>
          <meta name="robots" content="noindex" />
          <NotFoundView
            trail={trail}
            heading={t('help.article_not_found')}
            description={t('help.not_found_body')}
            actions={<BrowseTopicsLink />}
          />
        </>
      );
    }
    return <ErrorView trail={trail} onRetry={retry} retrying={retrying} />;
  }

  if (!article) {
    return (
      <PageShell
        breadcrumb={[...helpCrumbs, { label: t('common.loading') }]}
        title={
          <>
            <SkeletonText className="w-80" />
            <span className="sr-only">{t('common.loading')}</span>
          </>
        }
        meta={<SkeletonText className="w-24" />}
      >
        <ContentPanel>
          <span role="status" className="sr-only">
            {t('common.loading')}
          </span>
          {['w-full', 'w-11/12', 'w-4/5', 'w-2/3'].map((width) => (
            <SkeletonLine key={width} className={`mb-4 h-4 last:mb-0 ${width}`} />
          ))}
        </ContentPanel>
      </PageShell>
    );
  }

  const title = localized(article.title);
  const related = (category?.articles ?? [])
    .filter((item) => item.slug !== article.slug)
    .slice(0, RELATED_LIMIT);

  return (
    <PageShell
      breadcrumb={[
        ...helpCrumbs,
        { label: localized(article.category.name), href: `/help/c/${article.category.slug}` },
        { label: title },
      ]}
      title={title}
      meta={tPlural('help.view_count', article.views_count)}
    >
      {/* The server titles the page by its slug: reading the article there would count a view. */}
      <title>{`${title} · ${t('help.title')} | QBazaar`}</title>
      <ContentPanel>
        <MarkdownContent html={localized(article.body)} />
      </ContentPanel>
      <HelpFeedback className="mt-6" />
      {related.length > 0 ? (
        <section aria-labelledby="help-related-title" className="mt-10 qb-desktop:mt-12">
          <h2
            id="help-related-title"
            className="mb-4 font-qb text-qb-h4 font-semibold tracking-normal text-qb-ink qb-desktop:mb-6 qb-desktop:text-qb-h2"
          >
            {t('help.related_articles')}
          </h2>
          <ul className="flex flex-col gap-4">
            {related.map((item) => (
              <li key={item.id}>
                <HelpArticleCard article={item} />
              </li>
            ))}
          </ul>
        </section>
      ) : null}
      <HelpContactCard className="mt-10 qb-desktop:mt-12" />
    </PageShell>
  );
}
