import type { Metadata } from 'next';

import { CatalogHeader } from '@/components/catalog/CatalogHeader';
import { CategoryIndex, CategoryIndexStats } from '@/components/catalog/CategoryIndex';
import { siteFrame } from '@/components/design-system/site-frame';
import { headingFont } from '@/components/catalog/layout';
import { parsePage } from '@/components/catalog/listing-query';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { pageMetadata } from '@/lib/page-metadata';
import { cn } from '@/lib/utils';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return pageMetadata({ title: t('categories.all', 'الأقسام'), path: '/categories' });
}

interface PageProps {
  searchParams: Promise<{ page?: string | string[] }>;
}

/** All main categories — `/categories` (185:6576, 536:32620, 621:27193). */
export default async function CategoriesIndexPage({ searchParams }: PageProps) {
  await resolveServerLocale();
  const { page } = await searchParams;
  const title = t('categories.all', 'الأقسام');

  // all-categories.html: the title block on the fluid gutter, then the "Discover Categories" section below it.
  return (
    <main className={cn('bg-qb-page font-qb text-qb-ink', headingFont)}>
      <div className={cn(siteFrame, 'pt-[clamp(20px,4vw,40px)]')}>
        <CatalogHeader
          variant="index"
          breadcrumbOnPhones
          title={title}
          // The reference's trail ends in "Category"; the title stays until the copy pass adds the key.
          breadcrumb={[{ label: t('home.breadcrumb', 'الرئيسية'), href: '/' }, { label: t('catalog.index.crumb', title) }]}
          stats={<CategoryIndexStats />}
        />
      </div>
      <section aria-labelledby="discover-categories" className={cn(siteFrame, 'pt-[clamp(24px,4vw,44px)] pb-10')}>
        <div className="mx-auto max-w-[820px] text-center">
          <h2 id="discover-categories" className="font-qb text-[clamp(28px,4vw,40px)] leading-normal font-semibold tracking-normal text-qb-ink">
            {t('catalog.index.discover_start', 'اكتشف')} <span className="text-qb-brand">{t('catalog.index.discover_accent', 'الأقسام')}</span>
          </h2>
          <p className="mt-3.5 text-[clamp(15px,2vw,18px)] text-qb-ink-faint">
            {t('catalog.index.discover_text', 'تصفّح الأقسام المختلفة واعثر بسرعة على ما تحتاجه في مكان واحد.')}
          </p>
        </div>
        <CategoryIndex page={parsePage(typeof page === 'string' ? page : null)} />
      </section>
    </main>
  );
}
