import type { Metadata } from 'next';
import { Cairo, Inter, Geist_Mono } from 'next/font/google';
import { ThemeProvider } from 'next-themes';
import { Toaster } from '@/components/ui/sonner';
import { SiteHeaderGate } from '@/components/layout/SiteHeader';
import { SiteFooterGate } from '@/components/layout/SiteFooter';
import { Providers } from './providers';
import './globals.css';
import '../styles/qbfront.css';

const inter = Inter({
  subsets: ['latin'],
  weight: ['400', '500', '600', '700', '800'],
  variable: '--font-inter',
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
  title: {
    default: "QBazaar — Qatar's friendly classifieds marketplace",
    template: '%s · QBazaar',
  },
  description: 'QBazaar — buy, sell and discover near you in Qatar.',
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html
      lang="ar"
      dir="rtl"
      suppressHydrationWarning
      className={`${inter.variable} ${cairo.variable} ${geistMono.variable}`}
    >
      <body className="min-h-full flex flex-col">
        <ThemeProvider attribute="class" defaultTheme="light" enableSystem>
          <Providers>
            <SiteHeaderGate />
            <div className="flex-1">{children}</div>
            <SiteFooterGate />
          </Providers>
          <Toaster richColors closeButton position="top-center" />
        </ThemeProvider>
      </body>
    </html>
  );
}
