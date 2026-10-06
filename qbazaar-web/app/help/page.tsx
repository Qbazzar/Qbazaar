import type { Metadata } from 'next';

import { PageShell } from '@/components/design-system/PageShell';
import { HelpContactCard } from '@/components/help/HelpContactCard';
import { HelpHero } from '@/components/help/HelpHero';
import { HelpSearchBar } from '@/components/help/HelpSearchBar';
import { HelpTopics, HelpTopicsSummary } from '@/components/help/HelpTopics';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl } from '@/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('help.title'),
    description: t('help.subtitle'),
    alternates: { canonical: absoluteUrl('/help') },
  };
}

/**
 * Help center landing. No Figma frame: the page frame and hero search follow
 * all-categories (185:6576), with the help topics as its category tiles.
 */
export default async function HelpIndexPage() {
  // Pages render in parallel with the root layout, so prime the locale here too.
  await resolveServerLocale();

  return (
    <PageShell
      breadcrumb={[{ label: t('home.breadcrumb'), href: '/' }, { label: t('help.title') }]}
      title={t('help.title')}
      meta={<HelpTopicsSummary />}
    >
      <HelpHero
        id="help-search-title"
        titleLead={t('help.hero_lead')}
        titleAccent={t('help.hero_accent')}
        subtitle={t('help.subtitle')}
      >
        <HelpSearchBar />
      </HelpHero>
      <div className="qb-desktop:px-10">
        <HelpTopics />
        <HelpContactCard className="mt-10 qb-desktop:mt-12" />
      </div>
    </PageShell>
  );
}
