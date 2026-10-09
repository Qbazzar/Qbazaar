// Renders the default share cards (1200x630, one per language) into lib/og/cards with
// the site's own fonts, so Arabic is shaped by a real browser engine.
// Needs Playwright with Chrome, which is not a project dependency:
//   node scripts/generate-og-cards.mjs
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';
import path from 'node:path';
import { chromium } from 'playwright';

const root = process.cwd();
const url = (file) => pathToFileURL(path.join(root, file)).href;
const messages = (lang) => JSON.parse(readFileSync(`i18n/${lang}.json`, 'utf8')).brand;

const page = (lang) => `<!doctype html><html lang="${lang}" dir="${lang === 'ar' ? 'rtl' : 'ltr'}"><style>
@font-face { font-family: Poppins; font-weight: 600; src: url(${url('app/fonts/poppins-latin-600.woff2')}); }
@font-face { font-family: 'Noto Kufi Arabic'; font-weight: 100 900; src: url(${url('app/fonts/noto-kufi-arabic-variable.woff2')}); }
body { margin: 0; width: 1200px; height: 630px; box-sizing: border-box; background: #fff; border-bottom: 28px solid #f38057;
  display: flex; flex-direction: column; align-items: center; justify-content: center; }
img { width: 760px; display: block; }
p { margin: 36px 0 0; max-width: 1100px; text-align: center; font: 600 48px/1.3 Poppins, 'Noto Kufi Arabic'; color: #231f20; }
</style><body><img src="${url('public/brand/qb-logo.svg')}"><p>${messages(lang).tagline}</p></body></html>`;

const browser = await chromium.launch({ channel: 'chrome' });
const tab = await browser.newPage({ viewport: { width: 1200, height: 630 } });
for (const lang of ['ar', 'en']) {
  // A file page, not setContent(): about:blank may not load local fonts and images.
  const file = path.join(mkdtempSync(path.join(tmpdir(), 'og-')), `${lang}.html`);
  writeFileSync(file, page(lang));
  await tab.goto(pathToFileURL(file).href);
  await tab.evaluate(() => document.fonts.ready);
  await tab.screenshot({ path: `lib/og/cards/og-${lang}.png` });
}
await browser.close();
