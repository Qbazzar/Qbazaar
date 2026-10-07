import type { Metadata } from 'next';

import { CatalogHeader } from '@/components/catalog/CatalogHeader';
import { CategoryIndex, CategoryIndexStats } from '@/components/catalog/CategoryIndex';
import { catalogContainer, catalogPageTop, headingFont } from '@/components/catalog/layout';
import { parsePage } from '@/components/catalog/listing-query';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { cn } from '@/lib/utils';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('categories.all', 'الأقسام'),
  };
}

interface PageProps {
  searchParams: Promise<{ page?: string | string[] }>;
}

/** All main categories — `/categories` (185:6576, 536:32620, 621:27193). */
export default async function CategoriesIndexPage({ searchParams }: PageProps) {
  await resolveServerLocale();
  const { page } = await searchParams;
  const title = t('categories.all', 'الأقسام');

  return (
    <main className={cn('bg-qb-page font-qb text-qb-ink', headingFont)}>
      <div className={cn(catalogContainer, catalogPageTop)}>
        <CatalogHeader
          title={title}
          breadcrumb={[{ label: t('home.breadcrumb', 'الرئيسية'), href: '/' }, { label: title }]}
          stats={<CategoryIndexStats />}
        />
        <section aria-labelledby="discover-categories" className="mt-[50px] qb-desktop:mt-[77px]">
          <div className="mx-auto mb-[22px] max-w-[976px] text-center qb-tablet:mb-7 qb-desktop:mb-[26px]">
            <h2 id="discover-categories" className="font-qb text-qb-h3 leading-tight font-semibold tracking-normal text-qb-ink qb-tablet:text-[36px] qb-desktop:text-qb-h1">
              {t('catalog.index.discover_start', 'اكتشف')} <span className="text-qb-brand">{t('catalog.index.discover_accent', 'الأقسام')}</span>
            </h2>
            <p className="mx-auto mt-[19px] max-w-[311px] text-qb-micro text-qb-ink-muted qb-tablet:mt-[29px] qb-tablet:max-w-none qb-tablet:text-qb-body qb-desktop:mt-[22px] qb-desktop:text-qb-h3">
              {t('catalog.index.discover_text', 'تصفّح الأقسام المختلفة واعثر بسرعة على ما تحتاجه في مكان واحد.')}
            </p>
          </div>
          <CategoryIndex page={parsePage(typeof page === 'string' ? page : null)} />
        </section>
      </div>
    </main>
  );
}
