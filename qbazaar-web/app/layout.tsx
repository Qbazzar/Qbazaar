import type { Metadata } from 'next';
import { Cairo, DM_Sans, Instrument_Serif, Geist_Mono } from 'next/font/google';
import { ThemeProvider } from 'next-themes';
import { Toaster } from '@/components/ui/sonner';
import { SiteHeaderGate } from '@/components/layout/SiteHeader';
import { SiteFooterGate } from '@/components/layout/SiteFooter';
import { LocaleProvider } from '@/components/i18n/LocaleProvider';
import { dirFor } from '@/lib/i18n/locale';
import { resolveServerLocale } from '@/lib/i18n/server';
import { siteUrl } from '@/lib/seo';
import { Analytics } from '@vercel/analytics/next';
import { Providers } from './providers';
import './globals.css';
import '../styles/qbfront.css';

// Brand Latin faces (design system): DM Sans for body/UI, Instrument Serif for
// large display headings. Arabic always uses Cairo.
const dmSans = DM_Sans({
  subsets: ['latin'],
  weight: ['400', '500', '600', '700', '800'],
  variable: '--font-dm-sans',
});

const instrumentSerif = Instrument_Serif({
  subsets: ['latin'],
  weight: ['400'],
  style: ['normal', 'italic'],
  variable: '--font-instrument-serif',
});

// Cairo for all Arabic text (body + headings). User preference.
const cairo = Cairo({
  subsets: ['arabic'],
  weight: ['400', '500', '600', '700', '800', '900'],
  variable: '--font-cairo',
});

const geistMono = Geist_Mono({
  subsets: ['latin'],
  variable: '--font-mono',
});

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
      className={`${dmSans.variable} ${instrumentSerif.variable} ${cairo.variable} ${geistMono.variable}`}
    >
      <body className="min-h-full flex flex-col">
        <LocaleProvider locale={locale}>
          <ThemeProvider attribute="class" defaultTheme="light" enableSystem>
            <Providers>
              <SiteHeaderGate />
              <div className="flex-1">{children}</div>
              <SiteFooterGate />
            </Providers>
            <Toaster richColors closeButton position="top-center" />
          </ThemeProvider>
        </LocaleProvider>
        <Analytics />
      </body>
    </html>
  );
}
