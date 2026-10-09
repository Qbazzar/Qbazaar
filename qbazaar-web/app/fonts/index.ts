import localFont from 'next/font/local';

/*
 * Self-hosted faces (the Google Fonts subsets and the Noto Kufi Arabic release, SIL Open Font License 1.1), so
 * the build downloads nothing.
 *
 * next/font names each family after its const (`poppins`, its metric-matched
 * Arial fallback `poppins Fallback`) and exposes both in the `variable`. A
 * second file of the same family (another weight or subset) joins it by
 * declaring that name as its font-family. lib/fonts.test.ts checks these
 * names against the stacks in styles/design-tokens.css.
 *
 * New design: the families and weights Qbazaar-front uses. Poppins and the
 * Arabic body weight carry every page's text and are preloaded; the rest load
 * where they are used.
 */
const poppins = localFont({
  src: [
    { path: './poppins-latin-400.woff2', weight: '400' },
    { path: './poppins-latin-500.woff2', weight: '500' },
    { path: './poppins-latin-600.woff2', weight: '600' },
  ],
  variable: '--font-poppins',
});

// The reference sets totals, ratings and wallet amounts in 700; no home page text uses it.
const poppinsBold = localFont({
  src: './poppins-latin-700.woff2',
  weight: '700',
  declarations: [{ prop: 'font-family', value: 'poppins' }],
  variable: '--font-poppins-bold',
  preload: false,
  adjustFontFallback: false,
});

// One variable file (wght 100-900, Arabic blocks only) serves every Arabic weight, so Arabic pages
// download a single font and Latin letters and digits keep falling through to Poppins. Next can only
// preload per layout, not per locale, so English pages carry this preload too (about 110 KB).
const notoKufiArabic = localFont({
  src: './noto-kufi-arabic-variable.woff2',
  weight: '100 900',
  variable: '--font-noto-kufi-arabic',
});

const montserrat = localFont({
  src: './montserrat-latin-variable.woff2',
  weight: '400 600',
  variable: '--font-montserrat',
  preload: false,
});

const storyScript = localFont({
  src: './story-script-latin-400.woff2',
  weight: '400',
  variable: '--font-story-script',
  preload: false,
});

const dancingScript = localFont({
  src: './dancing-script-latin-700.woff2',
  weight: '700',
  variable: '--font-dancing-script',
  preload: false,
});

const acme = localFont({
  src: './acme-latin-400.woff2',
  weight: '400',
  variable: '--font-acme',
  preload: false,
});

const geistMono = localFont({
  src: './geist-mono-latin-variable.woff2',
  weight: '100 900',
  variable: '--font-mono',
  preload: false,
});

/** Class names for <html> that declare every face and its CSS variable. */
export const fontVariables = [
  poppins,
  poppinsBold,
  notoKufiArabic,
  montserrat,
  storyScript,
  dancingScript,
  acme,
  geistMono,
]
  .map((font) => font.variable)
  .join(' ');
