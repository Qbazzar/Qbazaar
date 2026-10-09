'use client';

/**
 * The company page's "Ads / About us / Legal Info" tabs
 * (seller-organization.html): one white segmented bar at every width, the
 * active tab orange. Below 1001 px an "Info" trigger under the bar opens the
 * company's contact card as a bottom sheet.
 */
import type { ReactNode } from 'react';

import { Tab, TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { formatNumber } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { BusinessProfile } from '@/lib/api/types';

import { hasCompanyContacts, safeWebsiteHref } from './CompanyInfoCard';
import { CompanyInfoSheet } from './CompanyInfoSheet';

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

const panelCard = 'rounded-qb-xl border border-qb-line bg-qb-surface p-[clamp(24px,3vw,34px)]';

export function CompanyTabs({ business, adsCount, locale, defaultTab, ads }: CompanyTabsProps) {
  return (
    <Tabs defaultValue={defaultTab}>
      <TabList className="flex-nowrap gap-2 rounded-[14px] border border-qb-line bg-qb-surface p-2 qb-desktop:gap-2">
        <CompanyTabTrigger value="ads">{t('users.profile.tabs.company_ads', { count: formatNumber(adsCount, locale) })}</CompanyTabTrigger>
        <CompanyTabTrigger value="about">{t('users.profile.tabs.about_us')}</CompanyTabTrigger>
        <CompanyTabTrigger value="legal">{t('users.profile.tabs.legal')}</CompanyTabTrigger>
      </TabList>

      {hasCompanyContacts(business) ? (
        <div className="mx-0.5 mt-7 mb-3.5 flex justify-end qb-desktop:hidden">
          <CompanyInfoSheet business={business} locale={locale} />
        </div>
      ) : null}

      <TabPanel value="ads" className="mt-6">
        {ads}
      </TabPanel>
      <TabPanel value="about" className="mt-6">
        <AboutPanel business={business} />
      </TabPanel>
      <TabPanel value="legal" className="mt-6">
        <LegalPanel business={business} />
      </TabPanel>
    </Tabs>
  );
}

/**
 * A tab of the segmented bar: 16 px / 500 in #4b4b4b on white, orange with
 * white text when active (also under the pointer, where the design-system
 * tab would turn it white); an inactive tab greys to #fafafa (273:4687).
 */
function CompanyTabTrigger({ value, children }: { value: CompanyTab; children: ReactNode }) {
  return (
    <Tab
      value={value}
      className={cn(
        // Three tabs share a phone's width, so a long label (Arabic "Legal Info") wraps instead of spilling over.
        'h-auto min-w-0 flex-1 rounded-qb-md border-0 bg-qb-surface px-1.5 py-[11px] text-center text-qb-label leading-tight font-medium whitespace-normal text-qb-ink-body shadow-none',
        'qb-tablet:h-auto qb-tablet:flex-[1_1_120px] qb-tablet:px-[18px] qb-tablet:py-3.5 qb-tablet:text-qb-body qb-tablet:leading-normal qb-desktop:h-auto qb-desktop:text-qb-body',
        'hover:bg-qb-hover data-active:font-medium data-active:hover:bg-qb-brand',
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
        <p dir="auto" className="text-qb-body-sm leading-[1.6] break-words whitespace-pre-line text-qb-ink-faint">
          {about}
        </p>
      ) : (
        <p className="text-qb-body-sm text-qb-ink-subtle">{t('users.profile.about_empty')}</p>
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
        <p className="text-qb-body-sm text-qb-ink-subtle">{t('users.profile.legal_empty')}</p>
      </div>
    );
  }

  return (
    <div className={`${panelCard} flex flex-col gap-[26px]`}>
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
      <h2 className="mb-2.5 text-qb-h5 font-semibold tracking-normal text-qb-ink">{title}</h2>
      <div className="flex flex-col gap-1 text-qb-body-sm leading-[1.6] text-qb-ink-faint">{children}</div>
    </section>
  );
}
