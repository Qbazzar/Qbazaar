import type { Metadata } from 'next';
import Link from 'next/link';
import { LifeBuoyIcon, MessageSquareIcon } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('support.title', 'الدعم الفني'),
    description: t('support.subtitle'),
  };
}

/**
 * Support landing — pure server component. Two big cards:
 *   • "Browse help center" → /help (read-first culture)
 *   • "Contact us"          → /support/new
 */
export default function SupportLandingPage() {
  return (
    <main>
      <div className="container" style={{ paddingTop: 32, paddingBottom: 64 }}>
        <div className="help-hero">
          <h1 className="help-hero__h">{t('support.title', 'كيف يمكننا مساعدتك؟')}</h1>
          <p className="help-hero__sub">
            {t(
              'support.subtitle',
              'ابدأ بمركز المساعدة، أو راسل فريق الدعم وسنرد خلال ساعات.',
            )}
          </p>
        </div>

        <div className="support-landing-grid">
          <Link href="/help" className="support-landing-card">
            <span className="support-landing-card__icon" aria-hidden>
              <LifeBuoyIcon className="size-6" />
            </span>
            <div className="support-landing-card__title">
              {t('support.browse_help', 'تصفّح مركز المساعدة')}
            </div>
            <p className="support-landing-card__sub">
              {t(
                'support.browse_help_sub',
                'مقالات شاملة عن النشر والشراء والأمان والمدفوعات.',
              )}
            </p>
          </Link>

          <Link href="/support/new" className="support-landing-card">
            <span className="support-landing-card__icon" aria-hidden>
              <MessageSquareIcon className="size-6" />
            </span>
            <div className="support-landing-card__title">
              {t('support.new_ticket', 'تواصل معنا')}
            </div>
            <p className="support-landing-card__sub">
              {t(
                'support.new_ticket_sub',
                'افتح تذكرة جديدة وسيتولّى فريق الدعم متابعتها معك.',
              )}
            </p>
          </Link>
        </div>
      </div>
    </main>
  );
}
