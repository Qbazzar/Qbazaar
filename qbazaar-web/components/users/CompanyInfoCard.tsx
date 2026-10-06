import { Clock, Globe, Mail, MapPin, Phone, type LucideIcon } from 'lucide-react';
import { useId, type ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { numberLocale } from '@/lib/ads/display';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { groupOpeningHours } from '@/lib/users/opening-hours';
import { cn } from '@/lib/utils';
import type { BusinessProfile } from '@/lib/api/types';

/** Only web links; anything else stays plain text. */
export function safeWebsiteHref(website: string): string | null {
  try {
    const url = new URL(website);
    return url.protocol === 'https:' || url.protocol === 'http:' ? url.href : null;
  } catch {
    return null;
  }
}

/** "https://www.example.qa/" -> "www.example.qa" */
function websiteLabel(website: string): string {
  return website.replace(/^https?:\/\//i, '').replace(/\/$/, '');
}

export function hasCompanyContacts(business: BusinessProfile | null): business is BusinessProfile {
  return Boolean(
    business &&
      (business.contact_phone || business.contact_email || business.website || business.address || business.opening_hours?.length),
  );
}

interface CompanyInfoListProps {
  business: BusinessProfile;
  locale: Locale;
}

/** Phone, email, website, address and opening hours, each with its icon tile. */
export function CompanyInfoList({ business, locale }: CompanyInfoListProps) {
  const website = business.website ? safeWebsiteHref(business.website) : null;
  const hours = groupOpeningHours(business.opening_hours ?? [], numberLocale(locale));

  return (
    <ul className="flex flex-col gap-3.5 text-qb-caption text-qb-ink-body">
      {business.contact_phone ? (
        <InfoItem icon={Phone} label={t('users.profile.contact.phone')}>
          <a href={`tel:${business.contact_phone}`} dir="ltr" className={cn('rounded-qb-xs hover:text-qb-brand', focusRing)}>
            {business.contact_phone}
          </a>
        </InfoItem>
      ) : null}
      {business.contact_email ? (
        <InfoItem icon={Mail} label={t('users.profile.contact.email')}>
          <a href={`mailto:${business.contact_email}`} className={cn('rounded-qb-xs break-all hover:text-qb-brand', focusRing)}>
            {business.contact_email}
          </a>
        </InfoItem>
      ) : null}
      {business.website ? (
        <InfoItem icon={Globe} label={t('users.profile.contact.website')}>
          {website ? (
            <a
              href={website}
              target="_blank"
              rel="noopener noreferrer nofollow"
              className={cn('rounded-qb-xs break-all hover:text-qb-brand', focusRing)}
            >
              {websiteLabel(business.website)}
            </a>
          ) : (
            <span className="break-all">{business.website}</span>
          )}
        </InfoItem>
      ) : null}
      {business.address ? (
        <InfoItem icon={MapPin} label={t('users.profile.contact.address')}>
          {business.address}
        </InfoItem>
      ) : null}
      {hours.length ? (
        <InfoItem icon={Clock} label={t('users.profile.contact.hours')}>
          <span className="flex flex-col gap-1">
            {hours.map((line) => (
              <span key={line.days}>
                {line.days}: <span dir="ltr">{line.hours ?? t('users.profile.contact.closed')}</span>
              </span>
            ))}
          </span>
        </InfoItem>
      ) : null}
    </ul>
  );
}

function InfoItem({ icon, label, children }: { icon: LucideIcon; label: string; children: ReactNode }) {
  return (
    <li className="flex items-center gap-2.5">
      <span className="flex size-[35px] shrink-0 items-center justify-center rounded-qb-md bg-qb-fill text-qb-ink-subtle">
        <Icon icon={icon} size="sm" label={label} />
      </span>
      <span className="min-w-0">{children}</span>
    </li>
  );
}

/** The "Info" panel of the company page's sidebar. */
export function CompanyInfoCard({ business, locale, className }: CompanyInfoListProps & { className?: string }) {
  const titleId = useId();
  return (
    <section
      aria-labelledby={titleId}
      className={cn('rounded-qb-2xl border border-qb-line bg-qb-surface px-[25px] pt-[30px] pb-6 font-qb shadow-qb-card', className)}
    >
      <h2 id={titleId} className="mb-5 text-qb-body-lg font-semibold tracking-normal text-qb-ink">
        {t('users.profile.info')}
      </h2>
      <CompanyInfoList business={business} locale={locale} />
    </section>
  );
}
