import type { Metadata } from 'next';
import { LifeBuoy, MessageSquare } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import { LinkTile } from '@/components/design-system/LinkTile';
import { PageShell } from '@/components/design-system/PageShell';
import { HelpHero } from '@/components/help/HelpHero';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl } from '@/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('support.title'),
    description: t('support.subtitle'),
    alternates: { canonical: absoluteUrl('/support') },
  };
}

/**
 * Support landing: the help center first, a ticket second. No Figma frame:
 * the all-categories hero (185:6576) over two of its tiles.
 */
export default async function SupportLandingPage() {
  // Pages render in parallel with the root layout, so prime the locale here too.
  await resolveServerLocale();

  return (
    <PageShell
      breadcrumb={[{ label: t('home.breadcrumb'), href: '/' }, { label: t('support.title') }]}
      title={t('support.title')}
    >
      <HelpHero
        id="support-options-title"
        titleLead={t('help.hero_lead')}
        titleAccent={t('help.hero_accent')}
        subtitle={t('support.subtitle')}
      />
      {/* `[display:grid]`, not `grid`: the old stylesheet's unlayered `.grid` rule would override the gaps. */}
      <ul
        aria-labelledby="support-options-title"
        className="mx-auto [display:grid] max-w-[976px] gap-3 qb-tablet:grid-cols-2 qb-desktop:gap-4"
      >
        <li>
          <LinkTile
            href="/help"
            icon={<Icon icon={LifeBuoy} size="lg" />}
            title={t('support.browse_help')}
            description={t('support.browse_help_sub')}
          />
        </li>
        <li>
          <LinkTile
            href="/support/new"
            icon={<Icon icon={MessageSquare} size="lg" />}
            title={t('support.new_ticket')}
            description={t('support.new_ticket_sub')}
          />
        </li>
      </ul>
    </PageShell>
  );
}
