import type { Metadata } from 'next';
import { ThemeProvider } from 'next-themes';
import { Toaster } from '@/components/ui/sonner';
import { SiteHeaderGate } from '@/components/layout/SiteHeader';
import { SiteFooter } from '@/components/layout/SiteFooter';
import { SiteFooterGate } from '@/components/layout/SiteFooterGate';
import { ImpersonationBanner } from '@/components/layout/ImpersonationBanner';
import { MAIN_CONTENT_ID } from '@/components/layout/main-content';
import { LocaleProvider } from '@/components/i18n/LocaleProvider';
import { dirFor } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { siteUrl } from '@/lib/seo';
import { fontVariables } from './fonts';
import { Providers } from './providers';
import './globals.css';
import '../styles/qbfront.css';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  const brand = t('brand.name', 'QBazaar');

  return {
    metadataBase: new URL(siteUrl()),
    title: {
      default: `${brand} — ${t('brand.tagline', 'سوق قطر الودود للإعلانات المبوبة')}`,
      template: `%s · ${brand}`,
    },
    description: t('brand.description', 'بِع واشترِ واكتشف ما حولك في قطر.'),
    openGraph: {
      siteName: brand,
      type: 'website',
    },
    twitter: {
      card: 'summary_large_image',
    },
  };
}

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const locale = await resolveServerLocale();

  return (
    <html
      lang={locale}
      dir={dirFor(locale)}
      suppressHydrationWarning
      // The sticky header is 88 px tall: keep focused and linked-to elements below it.
      className={`${fontVariables} scroll-pt-24`}
    >
      <body className="min-h-full flex flex-col">
        <LocaleProvider locale={locale}>
          {/* Light-only product — force light and ignore the OS/system theme. */}
          <ThemeProvider attribute="class" forcedTheme="light">
            <Providers>
              <SiteHeaderGate />
              <div id={MAIN_CONTENT_ID} tabIndex={-1} className="flex-1 outline-none">
                {children}
              </div>
              <SiteFooterGate>
                <SiteFooter />
              </SiteFooterGate>
              <ImpersonationBanner />
            </Providers>
            <Toaster richColors closeButton position="top-center" />
          </ThemeProvider>
        </LocaleProvider>
      </body>
    </html>
  );
}
