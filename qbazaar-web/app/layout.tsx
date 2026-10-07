import type { Metadata } from 'next';
import { ThemeProvider } from 'next-themes';
import { Toaster } from '@/components/ui/sonner';
import { SiteHeaderGate } from '@/components/layout/SiteHeader';
import { SiteFooterGate } from '@/components/layout/SiteFooter';
import { ImpersonationBanner } from '@/components/layout/ImpersonationBanner';
import { LocaleProvider } from '@/components/i18n/LocaleProvider';
import { dirFor } from '@/lib/i18n/locale';
import { resolveServerLocale } from '@/lib/i18n/server';
import { siteUrl } from '@/lib/seo';
import { fontVariables } from './fonts';
import { Providers } from './providers';
import './globals.css';
import '../styles/qbfront.css';

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl()),
  title: {
    default: "QBazaar — Qatar's friendly classifieds marketplace",
    template: '%s · QBazaar',
  },
  description: 'QBazaar — buy, sell and discover near you in Qatar.',
  openGraph: {
    siteName: 'QBazaar',
    type: 'website',
  },
  twitter: {
    card: 'summary_large_image',
  },
};

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const locale = await resolveServerLocale();

  return (
    <html
      lang={locale}
      dir={dirFor(locale)}
      suppressHydrationWarning
      className={fontVariables}
    >
      <body className="min-h-full flex flex-col">
        <LocaleProvider locale={locale}>
          {/* Light-only product — force light and ignore the OS/system theme. */}
          <ThemeProvider attribute="class" forcedTheme="light">
            <Providers>
              <SiteHeaderGate />
              <div className="flex-1">{children}</div>
              <SiteFooterGate />
              <ImpersonationBanner />
            </Providers>
            <Toaster richColors closeButton position="top-center" />
          </ThemeProvider>
        </LocaleProvider>
      </body>
    </html>
  );
}
