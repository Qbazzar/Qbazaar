import { readFile } from 'node:fs/promises';

import { resolveServerLocale } from '@/lib/i18n/server';

import { OG_CONTENT_TYPE } from './constants';

const ONE_DAY_SECONDS = 86_400;

/**
 * The default share card in the page language. The cards are pre-rendered
 * PNGs (scripts/generate-og-cards.mjs) so the Arabic is shaped by a real
 * browser engine, which next/og's renderer cannot do.
 */
export async function brandCardResponse(): Promise<Response> {
  const locale = await resolveServerLocale();
  const png = await readFile(new URL(`./cards/og-${locale}.png`, import.meta.url));

  return new Response(new Uint8Array(png), {
    headers: {
      'Content-Type': OG_CONTENT_TYPE,
      'Cache-Control': `public, max-age=${ONE_DAY_SECONDS}`,
      // Cached copies must not cross languages: the card follows the NEXT_LOCALE cookie.
      Vary: 'Cookie',
    },
  });
}
