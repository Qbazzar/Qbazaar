'use client';

/**
 * The company page's "Ads / About us / Legal Info" tabs (145:1063, 167:1827,
 * 167:2754): a segmented bar from 601 px, separate pills on phones.
 */
import { Info } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/design-system/Button';
import { cardVariants } from '@/components/design-system/Card';
import { Sheet } from '@/components/design-system/Modal';
import { Tab, TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { formatNumber } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { BusinessProfile } from '@/lib/api/types';

import { CompanyInfoList, hasCompanyContacts, safeWebsiteHref } from './CompanyInfoCard';

export type CompanyTab = 'ads' | 'about' | 'legal';

export function isCompanyTab(value: string | null): value is CompanyTab {
  return value === 'ads' || value === 'about' || value === 'legal';
}

interface CompanyTabsProps {
  business: BusinessProfile | null;
  adsCount: number;
  locale: Locale;
  defaultTab: CompanyTab;
  /** The ads grid. */
  ads: ReactNode;
}

const panelCard = cn(cardVariants({ large: true, elevated: true, padding: 'none' }), 'p-5 qb-tablet:px-[30px] qb-tablet:py-[38px]');

export function CompanyTabs({ business, adsCount, locale, defaultTab, ads }: CompanyTabsProps) {
  return (
    <Tabs defaultValue={defaultTab}>
      <TabList className="gap-2 qb-tablet:flex-nowrap qb-tablet:gap-0 qb-tablet:rounded-qb-xl qb-tablet:border qb-tablet:border-qb-line qb-tablet:bg-qb-surface qb-tablet:shadow-qb-card qb-desktop:gap-0">
        <CompanyTabTrigger value="ads">{t('users.profile.tabs.company_ads', { count: formatNumber(adsCount, locale) })}</CompanyTabTrigger>
        <CompanyTabTrigger value="about">{t('users.profile.tabs.about_us')}</CompanyTabTrigger>
        <CompanyTabTrigger value="legal">{t('users.profile.tabs.legal')}</CompanyTabTrigger>
      </TabList>

      {hasCompanyContacts(business) ? (
        <div className="mt-6 flex justify-end qb-desktop:hidden">
          <Sheet
            title={t('users.profile.info')}
            trigger={
              <Button variant="ghost" size="sm" className="text-qb-body-lg font-medium text-qb-ink">
                {t('users.profile.info')}
                <Info aria-hidden />
              </Button>
            }
          >
            <CompanyInfoList business={business} locale={locale} />
          </Sheet>
        </div>
      ) : null}

      <TabPanel value="ads" className="mt-6 qb-desktop:mt-8">
        {ads}
      </TabPanel>
      <TabPanel value="about" className="mt-6 qb-desktop:mt-8">
        <AboutPanel business={business} />
      </TabPanel>
      <TabPanel value="legal" className="mt-6 qb-desktop:mt-8">
        <LegalPanel business={business} />
      </TabPanel>
    </Tabs>
  );
}

function CompanyTabTrigger({ value, children }: { value: CompanyTab; children: ReactNode }) {
  return (
    <Tab
      value={value}
      className={cn(
        // Three pills share a phone's width, so a long label (Arabic "Legal Info") wraps instead of spilling over.
        'h-[51px] min-w-0 flex-1 rounded-qb-xl bg-qb-surface px-2 text-center text-qb-body-sm leading-tight font-medium whitespace-normal data-active:font-medium',
        'qb-tablet:h-14 qb-tablet:rounded-qb-xl qb-tablet:border-0 qb-tablet:px-4 qb-tablet:text-qb-body-lg qb-tablet:leading-normal qb-tablet:whitespace-nowrap qb-tablet:shadow-none',
        'qb-desktop:h-14 qb-desktop:text-qb-body-lg',
      )}
    >
      {children}
    </Tab>
  );
}

function AboutPanel({ business }: { business: BusinessProfile | null }) {
  const about = business?.about?.trim();
  return (
    <div className={panelCard}>
      {about ? (
        <p dir="auto" className="text-qb-body leading-[1.6] break-words whitespace-pre-line text-qb-ink-muted">
          {about}
        </p>
      ) : (
        <p className="text-qb-body text-qb-ink-subtle">{t('users.profile.about_empty')}</p>
      )}
    </div>
  );
}

function LegalPanel({ business }: { business: BusinessProfile | null }) {
  const imprint = [business?.legal_name, business?.address].filter(Boolean) as string[];
  const registration = business?.commercial_registration_number;
  const website = business?.website ? safeWebsiteHref(business.website) : null;
  const contact = [
    business?.contact_phone ? { label: t('users.profile.contact.phone'), value: business.contact_phone } : null,
    business?.contact_email ? { label: t('users.profile.contact.email'), value: business.contact_email } : null,
    website ? { label: t('users.profile.contact.website'), value: website } : null,
  ].filter((line): line is { label: string; value: string } => line !== null);

  if (!imprint.length && !registration && !contact.length) {
    return (
      <div className={panelCard}>
        <p className="text-qb-body text-qb-ink-subtle">{t('users.profile.legal_empty')}</p>
      </div>
    );
  }

  return (
    <div className={`${panelCard} flex flex-col gap-[30px]`}>
      {imprint.length || registration ? (
        <LegalSection title={t('users.profile.legal.imprint')}>
          {imprint.map((line) => (
            <p key={line}>
              <bdi>{line}</bdi>
            </p>
          ))}
          {registration ? (
            <p>
              {t('users.profile.legal.registration')}: <span dir="ltr">{registration}</span>
            </p>
          ) : null}
        </LegalSection>
      ) : null}
      {contact.length ? (
        <LegalSection title={t('users.profile.legal.contact')}>
          {contact.map((line) => (
            <p key={line.label} className="break-all">
              {/* Phone numbers, emails and links stay left-to-right inside Arabic text. */}
              {line.label}: <bdi dir="ltr">{line.value}</bdi>
            </p>
          ))}
        </LegalSection>
      ) : null}
    </div>
  );
}

function LegalSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section>
      <h2 className="text-qb-h5 font-medium tracking-normal text-qb-ink">{title}</h2>
      <div className="mt-3 flex flex-col gap-2.5 text-qb-caption text-qb-ink-subtle">{children}</div>
    </section>
  );
}
