import Link from 'next/link';
import type { ReactNode } from 'react';
import { Logo } from '@/components/ui/logo';
import { t } from '@/lib/i18n/messages';

/**
 * Split auth layout — QBFront port (source: QBFront/signin.html `.auth-card`).
 *
 * Two-column card: terracotta `.auth-card__pitch` on one side, the form on
 * the other. Hidden below 900px so mobile gets the form full-width (rule
 * lives in qbfront.css).
 */
export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="container" style={{ padding: '40px 0 80px' }}>
      <div className="auth-card">
        <div className="auth-card__pitch">
          <Link href="/" className="logo" aria-label={t('brand.name', 'QBazaar')}>
            <Logo inverted />
          </Link>
          <div>
            <h2 className="auth-card__pitch-h">
              {t('auth.hero.title_line1', 'سوقك المحلي')}
              <br />
              <em>{t('auth.hero.title_line2', 'بانتظارك')}</em>
            </h2>
            <p className="auth-card__pitch-body">
              {t(
                'auth.hero.subtitle',
                'انضم لآلاف القطريين الذين يبيعون ويشترون ويكتشفون كل يوم.',
              )}
            </p>
            <div className="auth-card__pitch-list">
              <div>
                <CheckGlyph />
                {t('auth.hero.bullet_free', 'النشر مجاني — دائماً')}
              </div>
              <div>
                <CheckGlyph />
                {t('auth.hero.bullet_verified', 'بائعون موثّقون وتقييمات حقيقية')}
              </div>
              <div>
                <CheckGlyph />
                {t('auth.hero.bullet_local', 'مصمّم لقطر، بتوقيتك المحلي')}
              </div>
            </div>
          </div>
          <div />
        </div>

        <div className="auth-card__form">{children}</div>
      </div>
    </div>
  );
}

function CheckGlyph() {
  return (
    <svg
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="m5 12 5 5 9-11" />
    </svg>
  );
}
