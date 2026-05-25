'use client';

/**
 * Global site footer — QBFront port.
 *
 * Mirrors the `<footer class="site-footer">` block in QBFront/index.html with
 * five link columns + brand panel + coral strip. The footer is auto-hidden on
 * routes that own their full-bleed chrome (auth, post-ad wizard).
 */
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Logo } from '@/components/ui/logo';
import { t } from '@/lib/i18n/messages';

const HIDE_FOOTER_PREFIXES = [
  '/login',
  '/register',
  '/forgot-password',
  '/reset-password',
  '/verify-otp',
  '/post-ad',
];

export function SiteFooterGate() {
  const pathname = usePathname() ?? '/';
  if (HIDE_FOOTER_PREFIXES.some((prefix) => pathname.startsWith(prefix))) {
    return null;
  }
  return <SiteFooter />;
}

export function SiteFooter() {
  return (
    <footer className="site-footer">
      <div className="container site-footer__inner">
        <div className="site-footer__cols">
          <div>
            <Link href="/" className="logo" aria-label={t('brand.name', 'QBazaar')}>
              <Logo />
            </Link>
            <p className="site-footer__brand-text">
              {t(
                'footer.brand_text',
                'سوق قطر للإعلانات المبوبة — يجمع البائعين بالمشترين بأمان وثقة.',
              )}
            </p>
            <div className="site-footer__socials">
              <a href="#" className="social-link social-link--primary" aria-label="Instagram">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
                  <rect x="3" y="3" width="18" height="18" rx="5" />
                  <circle cx="12" cy="12" r="4" />
                  <circle cx="17.5" cy="6.5" r="0.8" fill="currentColor" />
                </svg>
              </a>
              <a href="#" className="social-link" aria-label="Facebook">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
                  <path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v8h4v-8h3l1-4h-4V8z" />
                </svg>
              </a>
              <a href="#" className="social-link" aria-label="X">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
                  <path d="M4 4l16 16M20 4 4 20" />
                </svg>
              </a>
              <a href="#" className="social-link" aria-label="WhatsApp">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M21 12a8 8 0 0 1-12 7l-5 1 1-5A8 8 0 1 1 21 12z" />
                </svg>
              </a>
            </div>
          </div>

          <div>
            <div className="footer-col__title">{t('footer.col_classifieds', 'الإعلانات')}</div>
            <ul className="footer-col__links">
              <li><Link href="/about">{t('footer.about', 'من نحن')}</Link></li>
              <li><Link href="/ads">{t('footer.browse', 'تصفّح')}</Link></li>
              <li><Link href="/post-ad">{t('home.hero.cta_post', 'انشر إعلانك')}</Link></li>
              <li><Link href="/categories">{t('categories.all', 'كل الأقسام')}</Link></li>
            </ul>
          </div>

          <div>
            <div className="footer-col__title">{t('footer.col_information', 'معلومات')}</div>
            <ul className="footer-col__links">
              <li><Link href="/help">{t('footer.help', 'المساعدة')}</Link></li>
              <li><Link href="/help#safety">{t('footer.safety_tips', 'إرشادات الأمان')}</Link></li>
              <li><Link href="/help#report">{t('footer.report', 'الإبلاغ عن مشكلة')}</Link></li>
              <li><Link href="/help#privacy">{t('footer.privacy', 'سياسة الخصوصية')}</Link></li>
              <li><Link href="/help#terms">{t('footer.terms', 'شروط الاستخدام')}</Link></li>
            </ul>
          </div>

          <div>
            <div className="footer-col__title">{t('footer.col_account', 'الحساب')}</div>
            <ul className="footer-col__links">
              <li><Link href="/account">{t('account.nav.title', 'حسابي')}</Link></li>
              <li><Link href="/account/ads">{t('account.nav.ads', 'إعلاناتي')}</Link></li>
              <li><Link href="/account/favorites">{t('account.nav.favorites', 'المحفوظات')}</Link></li>
              <li><Link href="/account/messages">{t('account.nav.messages', 'الرسائل')}</Link></li>
            </ul>
          </div>

          <div>
            <div className="footer-col__title">{t('footer.col_discover', 'استكشف')}</div>
            <ul className="footer-col__links">
              <li><Link href="/search">{t('footer.search', 'البحث')}</Link></li>
              <li><Link href="/categories">{t('footer.categories', 'الأقسام')}</Link></li>
              <li><Link href="/ads?sort=newest">{t('footer.latest_ads', 'أحدث الإعلانات')}</Link></li>
            </ul>
          </div>
        </div>
      </div>

      <div className="site-footer__strip">
        <div className="container site-footer__strip-inner">
          <span>{t('footer.brand_short', 'QBAZAAR')}</span>
          <span className="site-footer__strip-right">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
              <path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" />
            </svg>{' '}
            {t('footer.copyright', '© QBazaar — جميع الحقوق محفوظة')}
          </span>
        </div>
      </div>
    </footer>
  );
}
