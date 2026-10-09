import { readFile } from 'node:fs/promises';

import type { Locale } from '@/lib/i18n/locale';
import { resolveServerLocale } from '@/lib/i18n/server';

import { OG_CONTENT_TYPE } from './constants';

const ONE_DAY_SECONDS = 86_400;

// Literal URLs, so the bundler can trace both files (a template string is not followed).
const CARDS: Record<Locale, URL> = {
  ar: new URL('./cards/og-ar.png', import.meta.url),
  en: new URL('./cards/og-en.png', import.meta.url),
};

/**
 * The default share card in the page language. The cards are pre-rendered
 * PNGs (scripts/generate-og-cards.mjs) so the Arabic is shaped by a real
 * browser engine, which next/og's renderer cannot do.
 */
export async function brandCardResponse(): Promise<Response> {
  const locale = await resolveServerLocale();
  const png = await readFile(CARDS[locale]);

  return new Response(new Uint8Array(png), {
    headers: {
      'Content-Type': OG_CONTENT_TYPE,
      'Cache-Control': `public, max-age=${ONE_DAY_SECONDS}`,
      // Cached copies must not cross languages: the card follows the NEXT_LOCALE cookie.
      Vary: 'Cookie',
    },
  });
}
