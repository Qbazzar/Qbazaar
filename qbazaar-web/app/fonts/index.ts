import localFont from 'next/font/local';

/*
 * Self-hosted faces (the Google Fonts subsets, SIL Open Font License 1.1), so
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

// Arabic pages need the body weight first; the others load as soon as the CSS asks for them, so
// English pages preload one Arabic file instead of three.
const ibmPlexArabic = localFont({
  src: './ibm-plex-sans-arabic-400.woff2',
  weight: '400',
  variable: '--font-ibm-plex-arabic',
});

const ibmPlexArabicWeights = localFont({
  src: [
    { path: './ibm-plex-sans-arabic-500.woff2', weight: '500' },
    { path: './ibm-plex-sans-arabic-600.woff2', weight: '600' },
    { path: './ibm-plex-sans-arabic-700.woff2', weight: '700' },
  ],
  declarations: [{ prop: 'font-family', value: 'ibmPlexArabic' }],
  variable: '--font-ibm-plex-arabic-weights',
  preload: false,
  adjustFontFallback: false,
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

/*
 * Old look (DM Sans, Instrument Serif, Cairo, Geist Mono) for the pages not yet
 * reskinned; app/globals.css reads them through these variables.
 */
const dmSans = localFont({
  src: './dm-sans-latin-variable.woff2',
  weight: '400 800',
  variable: '--font-dm-sans',
  preload: false,
});

const instrumentSerif = localFont({
  src: [
    { path: './instrument-serif-latin-400.woff2', style: 'normal' },
    { path: './instrument-serif-latin-400-italic.woff2', style: 'italic' },
  ],
  weight: '400',
  variable: '--font-instrument-serif',
  preload: false,
});

// Cairo comes in two subset files, so each declares the characters it covers.
const cairo = localFont({
  src: './cairo-arabic-variable.woff2',
  weight: '400 900',
  declarations: [{ prop: 'unicode-range', value: 'U+0600-06FF, U+0750-077F, U+0870-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FEFC' }],
  variable: '--font-cairo',
  preload: false,
});

const cairoLatin = localFont({
  src: './cairo-latin-variable.woff2',
  weight: '400 900',
  declarations: [
    { prop: 'font-family', value: 'cairo' },
    { prop: 'unicode-range', value: 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD' },
  ],
  variable: '--font-cairo-latin',
  preload: false,
  adjustFontFallback: false,
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
  ibmPlexArabic,
  ibmPlexArabicWeights,
  montserrat,
  storyScript,
  dancingScript,
  acme,
  dmSans,
  instrumentSerif,
  cairo,
  cairoLatin,
  geistMono,
]
  .map((font) => font.variable)
  .join(' ');
