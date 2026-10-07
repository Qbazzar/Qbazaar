import type { ReactNode } from 'react';
import Link from 'next/link';
import { ChevronDown, Copyright } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { SiteLogo } from './SiteLogo';

/**
 * Global site footer on the new design (728:44663; accordion columns under
 * 1000 px). A server component: the client gate in SiteFooterGate decides
 * whether to show it.
 */
interface FooterColumn {
  title: string;
  links: { href: string; label: string }[];
}

function footerColumns(): FooterColumn[] {
  return [
    {
      title: t('layout.footer.col_classifieds', 'الإعلانات'),
      links: [
        { href: '/p/about', label: t('layout.footer.about', 'من نحن') },
        { href: '/ads', label: t('layout.footer.browse', 'تصفّح الإعلانات') },
        { href: '/post-ad', label: t('layout.header.add_ad', 'أضف إعلاناً') },
        { href: '/categories', label: t('layout.footer.categories', 'كل الأقسام') },
      ],
    },
    {
      title: t('layout.footer.col_information', 'معلومات'),
      links: [
        { href: '/help', label: t('layout.footer.help', 'المساعدة') },
        { href: '/support', label: t('layout.footer.support', 'الدعم الفني') },
        { href: '/p/contact', label: t('layout.footer.contact', 'تواصل معنا') },
        { href: '/p/privacy', label: t('layout.footer.privacy', 'سياسة الخصوصية') },
        { href: '/p/terms', label: t('layout.footer.terms', 'شروط الاستخدام') },
      ],
    },
    {
      title: t('layout.footer.col_account', 'الحساب'),
      links: [
        { href: '/account', label: t('account.nav.title', 'حسابي') },
        { href: '/account/ads', label: t('layout.menu.my_ads', 'إعلاناتي') },
        { href: '/account/favorites', label: t('account.nav.favorites', 'المحفوظات') },
        { href: '/account/messages', label: t('account.nav.messages', 'الرسائل') },
      ],
    },
    {
      title: t('layout.footer.col_discover', 'استكشف'),
      links: [
        { href: '/search', label: t('layout.footer.search', 'البحث') },
        { href: '/categories', label: t('layout.menu.categories', 'الأقسام') },
        { href: '/ads?sort=newest', label: t('layout.footer.latest', 'أحدث الإعلانات') },
      ],
    },
  ];
}

const linkClass = cn('rounded-qb-xs text-qb-body-sm font-medium text-qb-ink-secondary hover:text-qb-brand', focusRing);

export function SiteFooter() {
  const columns = footerColumns();
  return (
    // Skipped until it nears the viewport, so the wordmark's font is not fetched during page load.
    <footer className="border-t border-qb-line bg-qb-page font-qb font-medium text-qb-ink [contain-intrinsic-size:auto_640px] [content-visibility:auto] qb-desktop:[contain-intrinsic-size:auto_400px]">
      <div className="mx-auto max-w-[1440px] px-5 pt-12 pb-7 qb-desktop:px-qb-gutter">
        <div className="flex flex-col gap-[26px] qb-desktop:flex-row qb-desktop:justify-between qb-desktop:gap-10">
          <FooterBrand />
          <div className="flex flex-col qb-desktop:max-w-[898px] qb-desktop:flex-1 qb-desktop:flex-row qb-desktop:justify-between qb-desktop:gap-16">
            {columns.map((column) => (
              <FooterColumnBlock key={column.title} column={column} />
            ))}
          </div>
        </div>
        <div className="mt-9 flex flex-col items-center gap-3 border-t border-qb-line-strong pt-5 text-center qb-desktop:flex-row qb-desktop:justify-between qb-desktop:text-start">
          <span className="font-qb-brand text-qb-body font-normal text-qb-brand">{t('layout.footer.wordmark', 'Q BAZAAR')}</span>
          <span className="flex items-center gap-2 text-qb-caption font-semibold text-qb-brand">
            <Icon icon={Copyright} className="size-[18px]" />
            {t('layout.footer.copyright', 'كيو بازار. جميع الحقوق محفوظة')}
          </span>
        </div>
      </div>
    </footer>
  );
}

function FooterBrand() {
  return (
    <div className="qb-desktop:max-w-[360px]">
      <Link href="/" aria-label={t('brand.name', 'QBazaar')} className={cn('mb-4 inline-block rounded-qb-sm', focusRing)}>
        <SiteLogo width={123} height={46} />
      </Link>
      <p className="mb-5 leading-6 text-qb-ink-secondary">{t('layout.footer.tagline', 'نربط العلامات التجارية بجمهورها عبر رسائل وتفاعل مدروس')}</p>
      {/* Not links until the brand's profile URLs exist. */}
      <div aria-hidden="true" className="flex gap-3">
        {SOCIALS.map((social, i) => (
          <span
            key={social.label}
            className={cn(
              'flex size-[38px] items-center justify-center rounded-full shadow-qb-header',
              i === 0 ? 'bg-qb-brand text-qb-on-brand' : 'bg-qb-surface text-qb-brand',
            )}
          >
            {social.icon}
          </span>
        ))}
      </div>
    </div>
  );
}

/** Heading + links on desktop; a collapsible row under 1000 px. Two renderings so neither needs client JS. */
function FooterColumnBlock({ column }: { column: FooterColumn }) {
  const links = (
    <ul className="flex flex-col gap-4">
      {column.links.map((link) => (
        <li key={link.href + link.label}>
          <Link href={link.href} className={linkClass}>
            {link.label}
          </Link>
        </li>
      ))}
    </ul>
  );
  return (
    <>
      <div className="hidden qb-desktop:block">
        <h3 className="mb-5 text-qb-h3 font-semibold tracking-normal">{column.title}</h3>
        {links}
      </div>
      <details className="group border-b border-qb-divider first-of-type:border-t qb-desktop:hidden">
        <summary className={cn('flex cursor-pointer list-none items-center justify-between px-0.5 py-4 text-qb-body font-semibold [&::-webkit-details-marker]:hidden', focusRing)}>
          {column.title}
          <Icon icon={ChevronDown} className="text-qb-ink-subtle transition-transform group-open:rotate-180" />
        </summary>
        <div className="pb-3.5">{links}</div>
      </details>
    </>
  );
}

/** The brand's social icons from the reference footer (Instagram first, highlighted). */
const SOCIALS: { label: string; icon: ReactNode }[] = [
  {
    label: 'Instagram',
    icon: (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
        <rect x="3" y="3" width="18" height="18" rx="5.5" />
        <circle cx="12" cy="12" r="4" />
        <circle cx="17.4" cy="6.6" r="1" fill="currentColor" stroke="none" />
      </svg>
    ),
  },
  {
    label: 'Facebook',
    icon: (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M14 9h3V5h-3c-2.2 0-4 1.8-4 4v2H7v4h3v6h4v-6h3l1-4h-4V9c0-.6.4-1 1-1z" />
      </svg>
    ),
  },
  {
    label: 'X',
    icon: (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22 5.9c-.7.3-1.4.5-2.2.6a3.9 3.9 0 0 0 1.7-2.2c-.8.5-1.6.8-2.5 1a3.9 3.9 0 0 0-6.7 3.6A11 11 0 0 1 4 4.6a3.9 3.9 0 0 0 1.2 5.2c-.6 0-1.2-.2-1.7-.5a3.9 3.9 0 0 0 3.1 3.9c-.5.1-1.1.2-1.7.1a3.9 3.9 0 0 0 3.6 2.7A7.9 7.9 0 0 1 2 17.6a11 11 0 0 0 6 1.8c7.2 0 11.1-6 11.1-11.1v-.5c.8-.6 1.4-1.3 1.9-2z" />
      </svg>
    ),
  },
  {
    label: 'LinkedIn',
    icon: (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M6.9 8.5H3.6V20h3.3V8.5zM5.2 3.4a1.9 1.9 0 1 0 0 3.9 1.9 1.9 0 0 0 0-3.9zM20.4 20v-6.3c0-3.4-1.8-5-4.2-5-1.9 0-2.8 1.1-3.2 1.8V8.5H9.6V20H13v-6.1c0-1.6.3-3.1 2.3-3.1 1.9 0 2 1.8 2 3.2V20h3.1z" />
      </svg>
    ),
  },
];
